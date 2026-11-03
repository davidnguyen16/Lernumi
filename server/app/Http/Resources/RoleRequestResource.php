<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class RoleRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->loadMissing('user', 'role');
        $data = [];
        foreach ($this->resource->attributesToArray() as $key => $value) $data[Str::camel($key)] = $value;
        $data['user'] = ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email];
        $data['role'] = ['id' => $this->role->id, 'roleCode' => $this->role->role_code, 'roleName' => $this->role->role_name, 'scopeType' => $this->role->scope_type];
        return $data;
    }
}
