<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ScopeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->loadMissing('roleAssignment.user', 'roleAssignment.role', 'offering.subject', 'offering.term');
        $data = [];
        foreach ($this->resource->attributesToArray() as $key => $value) $data[Str::camel($key)] = $value;
        $data['user'] = ['id' => $this->roleAssignment->user->id, 'name' => $this->roleAssignment->user->name, 'email' => $this->roleAssignment->user->email];
        $role = $this->roleAssignment->role;
        $data['role'] = ['id' => $role->id, 'roleCode' => $role->role_code, 'roleName' => $role->role_name, 'scopeType' => $role->scope_type];
        $offering = $this->offering;
        $data['offering'] = ['id' => $offering->id, 'institutionId' => $offering->institution_id, 'subjectId' => $offering->subject_id, 'subjectCode' => $offering->subject->subject_code, 'subjectName' => $offering->subject->subject_name, 'termId' => $offering->term_id, 'year' => $offering->term->year, 'session' => $offering->term->session];
        return $data;
    }
}
