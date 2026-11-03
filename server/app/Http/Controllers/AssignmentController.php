<?php

namespace App\Http\Controllers;

use App\Http\Requests\Access\AssignmentRequest;
use App\Http\Requests\Access\IndexAccessRequest;
use App\Http\Resources\ApiResource;
use App\Http\Resources\RoleAssignmentResource;
use App\Models\Institution;
use App\Models\Offering;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\AccessService;
use Illuminate\Http\Request;

class AssignmentController
{
    public function __construct(private readonly AccessService $access, private readonly AccessManagementService $management) {}

    public function index(IndexAccessRequest $request, Institution $institution)
    {
        $offering = $request->filled('offeringId') ? Offering::where('institution_id', $institution->id)->findOrFail($request->input('offeringId')) : null;
        // Complete assignments require institution authority; an offering projection hides all other scopes.
        $this->access->authorize($request->user(), 'role_assignment.view', $offering ?? $institution);
        $query = RoleAssignment::whereHas('role', fn ($q) => $q->where('institution_id', $institution->id))
            ->when(! $request->boolean('includeRevoked'), fn ($q) => $q->whereNull('revoked_at'))
            ->when($request->input('userId'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->input('roleId'), fn ($q, $id) => $q->where('role_id', $id))
            ->when($offering, fn ($q) => $q->where(fn ($assignments) => $assignments
                ->whereHas('role', fn ($roles) => $roles->where('scope_type', 'INSTITUTION'))
                ->orWhereHas('scopeAssignments', fn ($scopes) => $scopes->where('offering_id', $offering->id)->when(! $request->boolean('includeRevoked'), fn ($active) => $active->whereNull('revoked_at')))))
            ->with(['role', 'user', 'scopeAssignments' => fn ($scopes) => $scopes
                ->when($offering, fn ($q) => $q->where('offering_id', $offering->id))
                ->when(! $request->boolean('includeRevoked'), fn ($q) => $q->whereNull('revoked_at'))]);
        return RoleAssignmentResource::collection($query->orderByDesc('granted_at')->orderBy('id')->paginate($request->integer('perPage', 20))->withQueryString());
    }

    public function mine(IndexAccessRequest $request)
    {
        return RoleAssignmentResource::collection($request->user()->roleAssignments()->when(! $request->boolean('includeRevoked'), fn ($q) => $q->whereNull('revoked_at'))->with('role', 'user', 'scopeAssignments')->orderByDesc('granted_at')->paginate($request->integer('perPage', 20)));
    }

    public function store(AssignmentRequest $request, Institution $institution)
    {
        $data = $request->validated();
        $assignment = $this->management->transaction($institution, fn () => $this->management->grantRole($request->user(), $institution, User::findOrFail($data['userId']), Role::where('institution_id', $institution->id)->findOrFail($data['roleId']), $data['offeringIds'] ?? []));
        return (new RoleAssignmentResource($assignment))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Institution $institution, RoleAssignment $assignment)
    {
        abort_unless($assignment->role->institution_id === $institution->id, 404);
        return $this->management->transaction($institution, function () use ($request, $institution, $assignment) {
            $this->access->authorize($request->user(), 'role_assignment.revoke', $institution);
            $this->management->delegate($request->user(), $assignment->role, $institution);
            $assignment->refresh();
            if (! $assignment->revoked_at) {
                $assignment->update(['revoked_at' => now(), 'revoked_by_id' => $request->user()->id]);
                $assignment->scopeAssignments()->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by_id' => $request->user()->id]);
                $this->access->audit($request->user(), $institution->id, 'role_assignment.revoked', $assignment->id);
            }
            return response()->noContent();
        });
    }

    public function storeScope(Request $request, Institution $institution, RoleAssignment $assignment)
    {
        $data = $request->validate(['offeringId' => ['required', 'uuid']]);
        abort_unless($assignment->role->institution_id === $institution->id, 404);
        $offering = Offering::where('institution_id', $institution->id)->findOrFail($data['offeringId']);
        $scope = $this->management->transaction($institution, function () use ($request, $assignment, $offering, $institution) {
            $this->access->authorize($request->user(), 'scope_assignment.create', $offering);
            $this->management->delegate($request->user(), $assignment->role, $offering);
            $scope = $this->management->grantScopeUnchecked($request->user(), $assignment->refresh(), $offering);
            $this->access->audit($request->user(), $institution->id, 'scope_assignment.granted', $scope->id);
            return $scope;
        });
        return (new ApiResource($scope))->response()->setStatusCode(201);
    }

    public function destroyScope(Request $request, Institution $institution, RoleAssignment $assignment, ScopeAssignment $scope)
    {
        abort_unless($assignment->role->institution_id === $institution->id && $scope->role_assignment_id === $assignment->id, 404);
        return $this->management->transaction($institution, function () use ($request, $institution, $assignment, $scope) {
            $this->access->authorize($request->user(), 'scope_assignment.revoke', $scope->offering);
            $this->management->delegate($request->user(), $assignment->role, $scope->offering);
            if (! $scope->revoked_at) {
                $scope->update(['revoked_at' => now(), 'revoked_by_id' => $request->user()->id]);
                $this->access->audit($request->user(), $institution->id, 'scope_assignment.revoked', $scope->id);
            }
            return response()->noContent();
        });
    }

    public function members(IndexAccessRequest $request, Institution $institution)
    {
        $scope = $request->filled('offeringId') ? Offering::where('institution_id', $institution->id)->findOrFail($request->input('offeringId')) : $institution;
        $this->access->authorize($request->user(), 'member.view', $scope);
        $query = User::whereHas('roleAssignments', function ($q) use ($institution, $scope): void {
            $q->whereNull('revoked_at')->whereHas('role', fn ($r) => $r->where('institution_id', $institution->id));
            if ($scope instanceof Offering) $q->where(fn ($assignments) => $assignments->whereHas('role', fn ($roles) => $roles->where('scope_type', 'INSTITUTION'))->orWhereHas('scopeAssignments', fn ($scopes) => $scopes->where('offering_id', $scope->id)->whereNull('revoked_at')));
        })->when($request->input('search'), fn ($q, $search) => $q->whereRaw('strpos(lower(name), lower(?)) > 0 OR strpos(lower(email), lower(?)) > 0', [$search, $search]));
        $page = $query->orderBy('name')->orderBy('id')->paginate($request->integer('perPage', 20));
        return response()->json(['data' => $page->getCollection()->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'isActive' => $user->is_active]), 'meta' => ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'perPage' => $page->perPage(), 'total' => $page->total()]]);
    }
}
