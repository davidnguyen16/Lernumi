<?php

namespace App\Http\Controllers;

use App\Http\Requests\Access\IndexAccessRequest;
use App\Http\Resources\ApiResource;
use App\Models\Institution;
use App\Models\Offering;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignmentCode;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScopeCodeController
{
    public function __construct(private readonly AccessService $access, private readonly AccessManagementService $management) {}

    public function index(IndexAccessRequest $request, Institution $institution)
    {
        $ids = Offering::where('institution_id', $institution->id)->get()->filter(fn ($offering) => $this->access->can($request->user(), 'scope_assignment_code.view', $offering))->pluck('id');
        if (! $this->access->can($request->user(), 'scope_assignment_code.view', $institution) && $ids->isEmpty()) abort(403);
        return ApiResource::collection(ScopeAssignmentCode::whereIn('offering_id', $ids)->when($request->input('offeringId'), fn ($q, $id) => $q->where('offering_id', $id))->orderByDesc('created_at')->orderBy('id')->paginate($request->integer('perPage', 20)));
    }

    public function store(Request $request, Institution $institution)
    {
        $data = $request->validate(['offeringId' => ['required', 'uuid'], 'expiresAt' => ['sometimes', 'nullable', 'date', 'after:now', 'before:+1 year']]);
        $offering = Offering::where('institution_id', $institution->id)->findOrFail($data['offeringId']);
        $code = Str::random(40);
        $record = $this->management->transaction($institution, function () use ($request, $data, $offering, $code, $institution) {
            $this->access->authorize($request->user(), 'scope_assignment_code.create', $offering);
            $record = ScopeAssignmentCode::create(['offering_id' => $offering->id, 'created_by_id' => $request->user()->id, 'code_hash' => hash('sha256', $code), 'expires_at' => $data['expiresAt'] ?? now()->addDays(7)]);
            $this->access->audit($request->user(), $institution->id, 'scope_code.created', $record->id);
            return $record;
        });
        return response()->json(['data' => array_merge((new ApiResource($record))->resolve($request), ['code' => $code])], 201);
    }

    public function destroy(Request $request, Institution $institution, ScopeAssignmentCode $code)
    {
        $offering = Offering::where('institution_id', $institution->id)->findOrFail($code->offering_id);
        return $this->management->transaction($institution, function () use ($request, $institution, $offering, $code) {
            $this->access->authorize($request->user(), 'scope_assignment_code.revoke', $offering);
            $code = ScopeAssignmentCode::whereKey($code->id)->lockForUpdate()->firstOrFail();
            abort_if($code->used_at, 409, 'A used code cannot be revoked. Revoke the scope assignment instead.');
            if (! $code->revoked_at) {
                $code->update(['revoked_at' => now(), 'revoked_by_id' => $request->user()->id]);
                $this->access->audit($request->user(), $institution->id, 'scope_code.revoked', $code->id);
            }
            return response()->noContent();
        });
    }

    public function redeem(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100'], 'roleAssignmentId' => ['required', 'uuid']]);
        $code = ScopeAssignmentCode::where('code_hash', hash('sha256', $data['code']))->first();
        if (! $code) throw ValidationException::withMessages(['code' => 'This scope code is invalid.']);
        $offering = Offering::findOrFail($code->offering_id);
        $scope = $this->management->transaction($offering->institution, function () use ($request, $data, $code, $offering) {
            $code = ScopeAssignmentCode::whereKey($code->id)->lockForUpdate()->firstOrFail();
            abort_if($code->used_at || $code->revoked_at || ($code->expires_at && $code->expires_at->isPast()), 409, 'This code has expired or is no longer available.');
            $assignment = RoleAssignment::where('user_id', $request->user()->id)->whereNull('revoked_at')->findOrFail($data['roleAssignmentId']);
            $this->management->validateScope($assignment, $offering);
            abort_if($assignment->scopeAssignments()->where('offering_id', $offering->id)->whereNull('revoked_at')->exists(), 409, 'This role already has the offering scope.');
            // A generic offering code must never let its holder expand a higher role than its issuer.
            $issuer = User::findOrFail($code->created_by_id);
            $this->access->authorize($issuer, 'scope_assignment_code.create', $offering);
            $this->management->delegate($issuer, $assignment->role, $offering);
            $scope = $this->management->grantScopeUnchecked(null, $assignment, $offering, $code->id);
            $code->update(['used_at' => now()]);
            $this->access->audit($request->user(), $offering->institution_id, 'scope_code.redeemed', $code->id, ['scopeAssignmentId' => $scope->id]);
            return $scope;
        });
        return (new ApiResource($scope))->response()->setStatusCode(201);
    }
}
