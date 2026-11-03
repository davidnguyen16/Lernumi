<?php

namespace App\Http\Requests\Access;

use App\Services\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        $required = $this->isMethod('POST') || $this->isMethod('PUT') ? 'required' : 'sometimes';
        return ['roleCode' => [$required, 'string', 'max:100', 'regex:/^(institution|offering):[a-z0-9][a-z0-9._-]*$/'], 'roleName' => [$required, 'string', 'max:200'], 'scopeType' => [$required, Rule::in(['INSTITUTION', 'OFFERING'])], 'requestMode' => ['sometimes', Rule::in(['DISABLED', 'PUBLIC', 'CONDITIONAL'])], 'autoGrantEmailRegex' => ['sometimes', 'nullable', 'string', 'max:500'], 'permissions' => ['sometimes', 'array', 'max:100'], 'permissions.*' => ['required', 'string', 'distinct', Rule::in(PermissionCatalog::codes())], 'includedRoleIds' => ['sometimes', 'array', 'max:100'], 'includedRoleIds.*' => ['required', 'uuid', 'distinct'], 'requiredRoleIds' => ['sometimes', 'array', 'max:100'], 'requiredRoleIds.*' => ['required', 'uuid', 'distinct']];
    }
}
