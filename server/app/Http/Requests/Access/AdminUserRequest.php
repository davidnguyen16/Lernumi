<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->is_active && $this->user()?->is_admin; }
    protected function prepareForValidation(): void { if (is_string($this->email)) $this->merge(['email' => strtolower(trim($this->email))]); }
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        return ['name' => [$required, 'required', 'string', 'max:200'], 'firstName' => ['sometimes', 'nullable', 'string', 'max:100'], 'lastName' => ['sometimes', 'nullable', 'string', 'max:100'], 'email' => [$required, 'required', 'email:rfc', 'max:254', Rule::unique('users', 'email')->ignore($this->route('user')?->id)], 'password' => [$required, 'string', Password::min(10)->letters()->numbers(), new \App\Rules\PasswordByteLength], 'isAdmin' => ['sometimes', 'boolean'], 'isActive' => ['sometimes', 'boolean']];
    }
}
