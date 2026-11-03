<?php

namespace App\Http\Controllers;

use App\Http\Requests\Access\IndexAccessRequest;
use App\Http\Requests\Access\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Institution;
use App\Models\Role;
use App\Services\AccessManagementService;
use App\Services\AccessService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class RoleController
{
    public function __construct(private readonly AccessService $access, private readonly AccessManagementService $management) {}

    public function index(IndexAccessRequest $request, Institution $institution)
    {
        $this->access->authorize($request->user(), 'role.view', $institution);
        return RoleResource::collection(Role::query()->where('institution_id', $institution->id)->when($request->input('search'), fn ($q, $search) => $q->whereRaw('strpos(lower(role_code), lower(?)) > 0 OR strpos(lower(role_name), lower(?)) > 0', [$search, $search]))->orderBy('role_code')->paginate($request->integer('perPage', 20))->withQueryString());
    }

    public function store(RoleRequest $request, Institution $institution)
    {
        return (new RoleResource($this->management->saveRole($request->user(), $institution, $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Request $request, Institution $institution, Role $role)
    {
        abort_unless($role->institution_id === $institution->id, 404);
        $this->access->authorize($request->user(), 'role.view', $institution);
        return new RoleResource($role);
    }

    public function update(RoleRequest $request, Institution $institution, Role $role)
    {
        abort_unless($role->institution_id === $institution->id, 404);
        return new RoleResource($this->management->saveRole($request->user(), $institution, $request->validated(), $role));
    }

    public function destroy(Request $request, Institution $institution, Role $role)
    {
        abort_unless($role->institution_id === $institution->id, 404);
        return $this->management->transaction($institution, function () use ($request, $institution, $role) {
            $this->access->authorize($request->user(), 'role.delete', $institution);
            $this->management->delegate($request->user(), $role, $institution);
            try { $role->delete(); }
            catch (QueryException $error) { if ($error->getCode() === '23503') abort(409, 'This role is referenced by assignments, requests or another role.'); throw $error; }
            $this->access->audit($request->user(), $institution->id, 'role.deleted', $role->id);
            return response()->noContent();
        });
    }

    public function requestable(Request $request, Institution $institution)
    {
        return response()->json(['data' => Role::where('institution_id', $institution->id)->orderBy('role_name')->get()->filter(fn (Role $role) => $this->management->requestable($request->user(), $role))->map(fn (Role $role) => ['id' => $role->id, 'institutionId' => $role->institution_id, 'roleCode' => $role->role_code, 'roleName' => $role->role_name, 'scopeType' => $role->scope_type, 'requestMode' => $role->request_mode])->values()]);
    }

    public function join(Request $request, Institution $institution)
    {
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Verify your email before using automatic institution enrollment.');
        $this->management->transaction($institution, function () use ($request, $institution): void {
            foreach (Role::where('institution_id', $institution->id)->whereNotNull('auto_grant_email_regex')->get() as $role) {
                if (@preg_match($this->management->emailPattern($role->auto_grant_email_regex), $request->user()->email) === 1) $this->management->grantRoleUnchecked(null, $request->user(), $role);
            }
        });
        return response()->json(['data' => ['contexts' => $this->access->contexts($request->user())]]);
    }
}
