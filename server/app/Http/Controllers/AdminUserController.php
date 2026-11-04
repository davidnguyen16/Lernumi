<?php

namespace App\Http\Controllers;

use App\Http\Requests\Access\AdminUserRequest;
use App\Http\Requests\Access\IndexAccessRequest;
use App\Http\Resources\ApiResource;
use App\Http\Resources\UserResource;
use App\Models\Institution;
use App\Models\Permission;
use App\Models\User;
use App\Services\AccessService;
use App\Services\AuthSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController
{
    public function __construct(private readonly AccessService $access, private readonly AuthSessionService $sessions) {}

    public function index(IndexAccessRequest $request)
    {
        abort_unless($this->access->isAdmin($request->user()), 403);
        return UserResource::collection(User::withCount(['roleAssignments' => fn ($q) => $q->whereNull('revoked_at'), 'attempts'])->when($request->input('search'), fn ($q, $search) => $q->whereRaw('strpos(lower(name), lower(?)) > 0 OR strpos(lower(email), lower(?)) > 0', [$search, $search]))->orderBy('name')->orderBy('id')->paginate($request->integer('perPage', 20))->withQueryString());
    }

    public function show(Request $request, User $user)
    {
        abort_unless($this->access->isAdmin($request->user()), 403);
        return new UserResource($user->loadCount(['roleAssignments' => fn ($q) => $q->whereNull('revoked_at'), 'attempts']));
    }

    public function store(AdminUserRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = new User;
            $user->forceFill($this->attributes($request->validated()))->save();
            $this->access->audit($request->user(), null, 'user.created', $user->id);
            return $user;
        });
        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(AdminUserRequest $request, User $user)
    {
        $user = DB::transaction(function () use ($request, $user) {
            // Serialize all admin changes in a deterministic order to preserve a final active admin.
            $admins = User::where('is_admin', true)->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $attributes = $this->attributes($request->validated());
            if ($user->is_admin && $user->is_active && (($attributes['is_admin'] ?? true) === false || ($attributes['is_active'] ?? true) === false) && $admins->count() <= 1) abort(409, 'The final active system administrator cannot be disabled or demoted.');
            if (isset($attributes['email']) && $attributes['email'] !== $user->email) $attributes['email_verified_at'] = null;
            $user->forceFill($attributes)->save();
            if (! $user->is_active || isset($attributes['password'])) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->tokens()->delete();
                $this->sessions->revokeRuntime($user);
                $user->forceFill(['remember_token' => null])->save();
            }
            $this->access->audit($request->user(), null, 'user.updated', $user->id, ['fields' => array_keys($attributes)]);
            return $user;
        });
        return new UserResource($user);
    }

    public function permissions(Request $request)
    {
        $query = Permission::orderBy('code');
        if ($this->access->isAdmin($request->user())) $query->withCount('roles');
        return response()->json(['data' => $query->get()->map(fn ($permission) => ['code' => $permission->code, 'description' => $permission->description, 'institutionOnly' => \App\Services\PermissionCatalog::institutionOnly($permission->code), 'activeRoles' => $this->access->isAdmin($request->user()) ? $permission->roles_count : null])]);
    }

    public function audit(IndexAccessRequest $request, ?Institution $institution = null)
    {
        if ($institution) $this->access->authorize($request->user(), 'audit.view', $institution);
        else abort_unless($this->access->isAdmin($request->user()), 403);
        $page = DB::table('access_audit_logs')->when($institution, fn ($q) => $q->where('institution_id', $institution->id))->orderByDesc('created_at')->orderBy('id')->paginate($request->integer('perPage', 20));
        return response()->json(['data' => $page->getCollection()->map(fn ($log) => ['id' => $log->id, 'institutionId' => $log->institution_id, 'actorId' => $log->actor_id, 'action' => $log->action, 'subjectId' => $log->subject_id, 'metadata' => json_decode($log->metadata, true), 'createdAt' => $log->created_at]), 'meta' => ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'perPage' => $page->perPage(), 'total' => $page->total()]]);
    }

    private function attributes(array $data): array
    {
        $attributes = [];
        foreach (['name' => 'name', 'firstName' => 'first_name', 'lastName' => 'last_name', 'email' => 'email', 'password' => 'password', 'isAdmin' => 'is_admin', 'isActive' => 'is_active'] as $key => $column) if (array_key_exists($key, $data)) $attributes[$column] = $data[$key];
        return $attributes;
    }
}
