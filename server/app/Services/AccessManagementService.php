<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\Offering;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** All multi-table grants run under the institution lock to serialize approvals and revocations. */
class AccessManagementService
{
    public function __construct(private readonly AccessService $access) {}

    public function transaction(Institution $institution, callable $action): mixed
    {
        return DB::transaction(function () use ($institution, $action) {
            Institution::whereKey($institution->id)->lockForUpdate()->firstOrFail();
            return $action();
        });
    }

    public function saveRole(User $actor, Institution $institution, array $data, ?Role $role = null): Role
    {
        return $this->transaction($institution, function () use ($actor, $institution, $data, $role) {
            $this->access->authorize($actor, $role ? 'role.update' : 'role.create', $institution);
            if ($role) $role = Role::whereKey($role->id)->where('institution_id', $institution->id)->lockForUpdate()->firstOrFail();
            $scopeType = $data['scopeType'] ?? $role?->scope_type;
            $roleCode = $data['roleCode'] ?? $role?->role_code;
            if (! str_starts_with($roleCode, strtolower($scopeType).':')) throw ValidationException::withMessages(['roleCode' => 'The role code prefix must match scopeType.']);
            if (Role::where('institution_id', $institution->id)->where('role_code', $roleCode)->when($role, fn ($q) => $q->whereKeyNot($role->id))->exists()) throw ValidationException::withMessages(['roleCode' => 'This role code is already in use.']);
            if ($role && $scopeType !== $role->scope_type) throw ValidationException::withMessages(['scopeType' => 'A role scope cannot change. Create a new role instead.']);
            if (isset($data['autoGrantEmailRegex'])) {
                // Stored expressions use PCRE syntax without delimiters; bound work for each email.
                if (@preg_match($this->emailPattern($data['autoGrantEmailRegex']), '') === false) throw ValidationException::withMessages(['autoGrantEmailRegex' => 'This email regular expression is invalid.']);
            }
            $permissionCodes = $data['permissions'] ?? $role?->permissions()->pluck('permissions.code')->all() ?? [];
            if ($scopeType === 'OFFERING' && array_filter($permissionCodes, PermissionCatalog::institutionOnly(...))) throw ValidationException::withMessages(['permissions' => 'An offering role cannot grant institution-only permissions.']);
            $included = $data['includedRoleIds'] ?? $role?->includedRoles()->pluck('roles.id')->all() ?? [];
            $includedRoles = Role::whereIn('id', $included)->where('institution_id', $institution->id)->where('scope_type', $scopeType)->get();
            if ($includedRoles->count() !== count($included)) throw ValidationException::withMessages(['includedRoleIds' => 'Included roles must belong to the same institution and scope type.']);
            foreach ($includedRoles as $child) {
                if ($role && ($child->id === $role->id || $this->reaches($child, $role->id))) throw ValidationException::withMessages(['includedRoleIds' => 'Role inheritance cannot contain a cycle.']);
                $permissionCodes = array_merge($permissionCodes, $this->access->rolePermissions($child));
            }
            if (! $this->access->isAdmin($actor)) {
                foreach (PermissionCatalog::expand($permissionCodes) as $permission) if (! $this->access->can($actor, $permission, $institution)) abort(403, 'You may only delegate permissions you hold at institution scope.');
                // Editing a role with permissions one cannot grant would modify other members' authority.
                if ($role) foreach ($this->access->rolePermissions($role) as $permission) if (! $this->access->can($actor, $permission, $institution)) abort(403, 'You cannot edit a role with permissions above your own.');
            }
            $requiredIds = $data['requiredRoleIds'] ?? $role?->requiredRoles()->pluck('roles.id')->all() ?? [];
            if (Role::where('institution_id', $institution->id)->whereIn('id', $requiredIds)->count() !== count($requiredIds)) throw ValidationException::withMessages(['requiredRoleIds' => 'Required roles must belong to this institution.']);
            $attributes = ['institution_id' => $institution->id, 'role_code' => $roleCode, 'scope_type' => $scopeType];
            foreach (['roleName' => 'role_name', 'requestMode' => 'request_mode', 'autoGrantEmailRegex' => 'auto_grant_email_regex'] as $key => $column) if (array_key_exists($key, $data)) $attributes[$column] = $data[$key];
            if ($role) $role->update($attributes); else $role = Role::create($attributes);
            if (array_key_exists('permissions', $data)) $role->permissions()->sync($data['permissions']);
            if (array_key_exists('includedRoleIds', $data)) $role->includedRoles()->sync($included);
            if (array_key_exists('requiredRoleIds', $data)) $role->requiredRoles()->sync($requiredIds);
            $this->access->audit($actor, $institution->id, 'role.saved', $role->id, ['fields' => array_keys($data)]);
            return $role->refresh();
        });
    }

    public function reaches(Role $role, string $target, array $visited = []): bool
    {
        $queue = [$role];
        for ($index = 0; $index < count($queue); $index++) {
            $current = $queue[$index];
            if (isset($visited[$current->id])) continue;
            $visited[$current->id] = true;
            foreach ($current->includedRoles()->get() as $included) {
                if ($included->id === $target) return true;
                if (! isset($visited[$included->id])) $queue[] = $included;
            }
        }
        return false;
    }

    public function grantRole(User $actor, Institution $institution, User $user, Role $role, array $offeringIds = [], bool $approved = false): RoleAssignment
    {
        abort_unless($role->institution_id === $institution->id, 404);
        abort_unless($user->is_active, 422, 'The target account is disabled.');
        if ($role->scope_type === 'INSTITUTION') {
            if ($offeringIds) throw ValidationException::withMessages(['offeringIds' => 'Institution roles do not have offering scopes.']);
            $this->access->authorize($actor, 'role_assignment.create', $institution);
            $this->delegate($actor, $role, $institution);
        } elseif ($offeringIds) {
            foreach ($offeringIds as $id) {
                $offering = Offering::where('institution_id', $institution->id)->findOrFail($id);
                $this->access->authorize($actor, 'role_assignment.create', $offering);
                $this->delegate($actor, $role, $offering);
            }
        } else {
            $this->access->authorize($actor, 'role_assignment.create', $institution);
            $this->delegate($actor, $role, $institution);
        }
        return $this->grantRoleUnchecked($actor, $user, $role, $offeringIds, $approved);
    }

    public function grantRoleUnchecked(?User $actor, User $user, Role $role, array $offeringIds = [], bool $approved = false): RoleAssignment
    {
        $assignment = RoleAssignment::where('user_id', $user->id)->where('role_id', $role->id)->whereNull('revoked_at')->first();
        if (! $assignment) $assignment = RoleAssignment::create(['user_id' => $user->id, 'role_id' => $role->id, 'granted_by_id' => $approved ? null : $actor?->id, 'granted_at' => now()]);
        foreach ($offeringIds as $id) $this->grantScopeUnchecked($actor, $assignment, Offering::where('institution_id', $role->institution_id)->findOrFail($id));
        $this->access->audit($actor, $role->institution_id, 'role_assignment.granted', $assignment->id, ['userId' => $user->id, 'roleId' => $role->id]);
        return $assignment->load('role', 'scopeAssignments');
    }

    public function delegate(User $actor, Role $role, Institution|Offering $scope): void
    {
        if ($this->access->isAdmin($actor)) return;
        foreach ($this->access->rolePermissions($role) as $permission) if (! $this->access->can($actor, $permission, $scope)) abort(403, 'You may only grant role permissions you hold in this scope.');
    }

    public function validateScope(RoleAssignment $assignment, Offering $offering): void
    {
        abort_unless(! $assignment->revoked_at && $assignment->role->scope_type === 'OFFERING' && $assignment->role->institution_id === $offering->institution_id, 422, 'The active offering role and offering must belong to the same institution.');
    }

    public function grantScopeUnchecked(?User $actor, RoleAssignment $assignment, Offering $offering, ?string $codeId = null): ScopeAssignment
    {
        $this->validateScope($assignment, $offering);
        return ScopeAssignment::firstOrCreate(['role_assignment_id' => $assignment->id, 'offering_id' => $offering->id, 'revoked_at' => null], ['granted_by_id' => $actor?->id, 'scope_assignment_code_id' => $codeId, 'granted_at' => now()]);
    }

    public function requestable(User $user, Role $role): bool
    {
        if ($role->request_mode === 'PUBLIC') return true;
        if ($role->request_mode !== 'CONDITIONAL') return false;
        return $user->roleAssignments()->whereNull('revoked_at')->whereIn('role_id', $role->requiredRoles()->pluck('roles.id'))->exists();
    }

    public function emailPattern(string $expression): string
    {
        return '~(*LIMIT_MATCH=10000)(*LIMIT_DEPTH=100)'.str_replace('~', '\\~', $expression).'~iD';
    }
}
