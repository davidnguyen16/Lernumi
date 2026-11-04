<?php

namespace App\Http\Resources;

use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'institutionId' => $this->institution_id, 'roleCode' => $this->role_code, 'roleName' => $this->role_name, 'scopeType' => $this->scope_type, 'requestMode' => $this->request_mode, 'autoGrantEmailRegex' => $this->auto_grant_email_regex, 'permissions' => $this->permissions()->pluck('permissions.code')->all(), 'effectivePermissions' => app(AccessService::class)->rolePermissions($this->resource), 'includedRoleIds' => $this->includedRoles()->pluck('roles.id')->all(), 'requiredRoleIds' => $this->requiredRoles()->pluck('roles.id')->all(), 'counts' => $this->whenCounted('assignments', fn () => ['assignments' => $this->assignments_count, 'requests' => $this->requests_count]), 'createdAt' => $this->created_at?->toISOString(), 'updatedAt' => $this->updated_at?->toISOString()];
    }
}
