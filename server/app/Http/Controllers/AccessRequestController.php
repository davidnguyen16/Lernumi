<?php

namespace App\Http\Controllers;

use App\Http\Requests\Access\IndexAccessRequest;
use App\Http\Resources\RoleRequestResource;
use App\Http\Resources\ScopeRequestResource;
use App\Models\Institution;
use App\Models\Offering;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\RoleRequest;
use App\Models\ScopeAssignmentRequest;
use App\Services\AccessManagementService;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccessRequestController
{
    public function __construct(private readonly AccessService $access, private readonly AccessManagementService $management) {}

    public function roles(IndexAccessRequest $request, Institution $institution)
    {
        $this->access->authorize($request->user(), 'role_request.view', $institution);
        return RoleRequestResource::collection(RoleRequest::with('user', 'role')->where('institution_id', $institution->id)->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))->when($request->input('userId'), fn ($q, $id) => $q->where('user_id', $id))->orderByDesc('requested_at')->orderBy('id')->paginate($request->integer('perPage', 20)));
    }

    public function myRoles(IndexAccessRequest $request)
    {
        return RoleRequestResource::collection(RoleRequest::with('user', 'role')->where('user_id', $request->user()->id)->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))->orderByDesc('requested_at')->paginate($request->integer('perPage', 20)));
    }

    public function storeRole(Request $request, Institution $institution)
    {
        $data = $request->validate(['roleId' => ['required', 'uuid'], 'note' => ['sometimes', 'nullable', 'string', 'max:2000']]);
        $role = Role::where('institution_id', $institution->id)->findOrFail($data['roleId']);
        $application = $this->management->transaction($institution, function () use ($request, $data, $role, $institution) {
            abort_unless($this->management->requestable($request->user(), $role), 403, 'This role cannot be requested by your account.');
            if (RoleAssignment::where('user_id', $request->user()->id)->where('role_id', $role->id)->whereNull('revoked_at')->exists()) abort(409, 'You already hold this role.');
            if (RoleRequest::where('user_id', $request->user()->id)->where('role_id', $role->id)->where('status', 'PENDING')->exists()) abort(409, 'A pending request already exists.');
            $application = RoleRequest::create(['user_id' => $request->user()->id, 'institution_id' => $institution->id, 'role_id' => $role->id, 'note' => $data['note'] ?? null, 'requested_at' => now(), 'status' => 'PENDING']);
            $this->access->audit($request->user(), $institution->id, 'role_request.created', $application->id, ['note' => $data['note'] ?? null]);
            return $application;
        });
        return (new RoleRequestResource($application))->response()->setStatusCode(201);
    }

    public function reviewRole(Request $request, Institution $institution, RoleRequest $application)
    {
        abort_unless($application->institution_id === $institution->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:APPROVED,REJECTED'], 'note' => ['sometimes', 'nullable', 'string', 'max:2000']]);
        $application = $this->management->transaction($institution, function () use ($request, $data, $institution, $application) {
            $application = RoleRequest::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($request->user(), $data['decision'] === 'APPROVED' ? 'role_request.approve' : 'role_request.reject', $institution);
            abort_unless($application->status === 'PENDING', 409, 'This request has already been processed.');
            abort_if($application->user_id === $request->user()->id && ! $this->access->isAdmin($request->user()), 403, 'You cannot review your own request.');
            $attributes = ['status' => $data['decision'], 'processed_at' => now(), 'processed_by_id' => $request->user()->id];
            if (array_key_exists('note', $data)) $attributes['note'] = $data['note'];
            if ($data['decision'] === 'APPROVED') {
                $this->management->delegate($request->user(), $application->role, $institution);
                abort_unless($application->user->is_active, 422, 'This account is disabled.');
                $attributes['approved_assignment_id'] = $this->management->grantRoleUnchecked($request->user(), $application->user, $application->role, [], true)->id;
            }
            $application->update($attributes);
            $this->access->audit($request->user(), $institution->id, 'role_request.'.strtolower($data['decision']), $application->id, ['decisionNote' => $data['note'] ?? null]);
            return $application;
        });
        return new RoleRequestResource($application);
    }

    public function scopes(IndexAccessRequest $request, Institution $institution)
    {
        $ids = Offering::where('institution_id', $institution->id)->get()->filter(fn ($offering) => $this->access->can($request->user(), 'scope_request.view', $offering))->pluck('id');
        if (! $this->access->can($request->user(), 'scope_request.view', $institution) && $ids->isEmpty()) abort(403);
        return ScopeRequestResource::collection(ScopeAssignmentRequest::with('roleAssignment.user', 'roleAssignment.role', 'offering.subject', 'offering.term')->whereIn('offering_id', $ids)->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))->when($request->input('offeringId'), fn ($q, $id) => $q->where('offering_id', $id))->orderByDesc('requested_at')->orderBy('id')->paginate($request->integer('perPage', 20)));
    }

    public function myScopes(IndexAccessRequest $request)
    {
        return ScopeRequestResource::collection(ScopeAssignmentRequest::with('roleAssignment.user', 'roleAssignment.role', 'offering.subject', 'offering.term')->whereHas('roleAssignment', fn ($q) => $q->where('user_id', $request->user()->id))->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))->orderByDesc('requested_at')->paginate($request->integer('perPage', 20)));
    }

    public function storeScope(Request $request, Institution $institution)
    {
        $data = $request->validate(['roleAssignmentId' => ['required', 'uuid'], 'offeringId' => ['required', 'uuid'], 'note' => ['sometimes', 'nullable', 'string', 'max:2000']]);
        $offering = Offering::where('institution_id', $institution->id)->findOrFail($data['offeringId']);
        $application = $this->management->transaction($institution, function () use ($request, $data, $offering, $institution) {
            $assignment = RoleAssignment::where('user_id', $request->user()->id)->whereNull('revoked_at')->findOrFail($data['roleAssignmentId']);
            $this->management->validateScope($assignment, $offering);
            if ($assignment->scopeAssignments()->where('offering_id', $offering->id)->whereNull('revoked_at')->exists()) abort(409, 'You already have access to this offering.');
            if (ScopeAssignmentRequest::where('role_assignment_id', $assignment->id)->where('offering_id', $offering->id)->where('status', 'PENDING')->exists()) abort(409, 'A pending request already exists.');
            $application = ScopeAssignmentRequest::create(['role_assignment_id' => $assignment->id, 'offering_id' => $offering->id, 'note' => $data['note'] ?? null, 'requested_at' => now(), 'status' => 'PENDING']);
            $this->access->audit($request->user(), $institution->id, 'scope_request.created', $application->id, ['note' => $data['note'] ?? null]);
            return $application;
        });
        return (new ScopeRequestResource($application))->response()->setStatusCode(201);
    }

    public function reviewScope(Request $request, Institution $institution, ScopeAssignmentRequest $application)
    {
        abort_unless($application->offering->institution_id === $institution->id, 404);
        $data = $request->validate(['decision' => ['required', 'in:APPROVED,REJECTED'], 'note' => ['sometimes', 'nullable', 'string', 'max:2000']]);
        $application = $this->management->transaction($institution, function () use ($request, $data, $institution, $application) {
            $application = ScopeAssignmentRequest::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($request->user(), $data['decision'] === 'APPROVED' ? 'scope_request.approve' : 'scope_request.reject', $application->offering);
            abort_unless($application->status === 'PENDING', 409, 'This request has already been processed.');
            abort_if($application->roleAssignment->user_id === $request->user()->id && ! $this->access->isAdmin($request->user()), 403, 'You cannot review your own request.');
            $attributes = ['status' => $data['decision'], 'processed_at' => now(), 'processed_by_id' => $request->user()->id];
            if (array_key_exists('note', $data)) $attributes['note'] = $data['note'];
            if ($data['decision'] === 'APPROVED') {
                $this->management->delegate($request->user(), $application->roleAssignment->role, $application->offering);
                $attributes['approved_scope_assignment_id'] = $this->management->grantScopeUnchecked($request->user(), $application->roleAssignment, $application->offering)->id;
            }
            $application->update($attributes);
            $this->access->audit($request->user(), $institution->id, 'scope_request.'.strtolower($data['decision']), $application->id, ['decisionNote' => $data['note'] ?? null]);
            return $application;
        });
        return new ScopeRequestResource($application);
    }
}
