<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\Offering;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Scoped RBAC evaluation. Revoked assignments and scopes never grant permissions. */
class AccessService
{
    public function isAdmin(User $user): bool
    {
        return $user->is_active && $user->is_admin;
    }

    public function can(User $user, string $permission, Institution|Offering $scope): bool
    {
        return $this->canInContext($user, $permission, $scope instanceof Institution ? $scope->id : $scope->institution_id, $scope instanceof Offering ? $scope->id : null);
    }

    public function authorize(User $user, string $permission, Institution|Offering $scope): void
    {
        abort_unless($this->can($user, $permission, $scope), 403, 'You do not have permission in this scope.');
    }

    public function canInContext(User $user, string $permission, string $institutionId, ?string $offeringId = null, ?string $roleAssignmentId = null): bool
    {
        if (! $user->is_active || ! in_array($permission, PermissionCatalog::codes(), true)) return false;
        if (! \Illuminate\Support\Str::isUuid($institutionId) || ($offeringId !== null && ! \Illuminate\Support\Str::isUuid($offeringId)) || ($roleAssignmentId !== null && ! \Illuminate\Support\Str::isUuid($roleAssignmentId))) return false;
        if ($this->isAdmin($user)) return true;
        // An explicit offering must belong to the institution being checked.
        if ($offeringId && ! Offering::query()->whereKey($offeringId)->where('institution_id', $institutionId)->exists()) return false;
        $assignments = $user->roleAssignments()->whereNull('revoked_at')
            ->whereHas('role', fn ($q) => $q->where('institution_id', $institutionId))
            ->when($roleAssignmentId, fn ($q) => $q->whereKey($roleAssignmentId))
            ->with(['role', 'scopeAssignments' => fn ($q) => $q->whereNull('revoked_at')])->get();
        if ($permission === 'institution.view') return $assignments->isNotEmpty();
        $permissions = [];
        foreach ($assignments as $assignment) {
            if ($assignment->role->scope_type === 'OFFERING') {
                if (! $offeringId || PermissionCatalog::institutionOnly($permission) || ! $assignment->scopeAssignments->contains('offering_id', $offeringId)) continue;
            }
            $permissions = array_merge($permissions, $this->rolePermissions($assignment->role));
        }
        return in_array($permission, PermissionCatalog::expand($permissions), true);
    }

    public function rolePermissions(Role $role, array $visited = []): array
    {
        $queue = [$role];
        $permissions = [];
        for ($index = 0; $index < count($queue); $index++) {
            $current = $queue[$index];
            if (isset($visited[$current->id])) continue;
            $visited[$current->id] = true;
            $permissions = array_merge($permissions, $current->permissions()->pluck('permissions.code')->all());
            foreach ($current->includedRoles()->get() as $included) {
                // Defense against invalid direct database edits crossing tenants or scopes.
                if ($included->institution_id !== $role->institution_id || $included->scope_type !== $role->scope_type || isset($visited[$included->id])) continue;
                $queue[] = $included;
            }
        }
        return PermissionCatalog::expand(array_values(array_unique($permissions)));
    }

    public function offeringIds(User $user, string $permission = 'offering.view'): array
    {
        if (! $user->is_active) return [];
        return Offering::query()->get(['id', 'institution_id'])->filter(fn (Offering $offering) => $this->can($user, $permission, $offering))->pluck('id')->values()->all();
    }

    public function institutionIds(User $user, string $permission = 'institution.view'): array
    {
        if (! $user->is_active) return [];
        return Institution::query()->get()->filter(fn (Institution $institution) => $this->can($user, $permission, $institution))->pluck('id')->values()->all();
    }

    public function contexts(User $user): array
    {
        if (! $user->is_active) return [];
        return $user->roleAssignments()->whereNull('revoked_at')->with(['role.institution', 'scopeAssignments' => fn ($q) => $q->whereNull('revoked_at')])->get()->map(fn (RoleAssignment $assignment) => [
            'id' => $assignment->id, 'roleAssignmentId' => $assignment->id,
            'institution' => ['id' => $assignment->role->institution_id, 'name' => $assignment->role->institution->name],
            'role' => ['id' => $assignment->role_id, 'roleCode' => $assignment->role->role_code, 'roleName' => $assignment->role->role_name, 'scopeType' => $assignment->role->scope_type],
            'permissions' => $this->rolePermissions($assignment->role),
            'offeringIds' => $assignment->role->scope_type === 'OFFERING' ? $assignment->scopeAssignments->pluck('offering_id')->values()->all() : Offering::where('institution_id', $assignment->role->institution_id)->pluck('id')->all(),
        ])->all();
    }

    public function audit(?User $actor, ?string $institutionId, string $action, ?string $subjectId = null, array $metadata = []): void
    {
        DB::table('access_audit_logs')->insert(['id' => (string) \Illuminate\Support\Str::uuid(), 'institution_id' => $institutionId, 'actor_id' => $actor?->id, 'action' => $action, 'subject_id' => $subjectId, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
