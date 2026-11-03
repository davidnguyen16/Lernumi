<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->loadMissing('role', 'user', 'scopeAssignments');
        return ['id' => $this->id, 'userId' => $this->user_id, 'user' => ['id' => $this->user_id, 'name' => $this->user->name, 'email' => $this->user->email], 'roleId' => $this->role_id, 'role' => ['id' => $this->role_id, 'roleCode' => $this->role->role_code, 'roleName' => $this->role->role_name, 'scopeType' => $this->role->scope_type], 'institutionId' => $this->role->institution_id, 'grantedById' => $this->granted_by_id, 'grantedAt' => $this->granted_at?->toISOString(), 'revokedAt' => $this->revoked_at?->toISOString(), 'revokedById' => $this->revoked_by_id, 'scopeAssignments' => ApiResource::collection($this->scopeAssignments)];
    }
}
