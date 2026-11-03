<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    protected function prepareForValidation(): void { if (is_string($this->email)) $this->merge(['email' => strtolower(trim($this->email))]); }
    public function rules(): array { return ['name' => ['sometimes', 'required', 'string', 'max:200'], 'firstName' => ['sometimes', 'nullable', 'string', 'max:100'], 'lastName' => ['sometimes', 'nullable', 'string', 'max:100'], 'email' => ['sometimes', 'required', 'email:rfc', 'max:254', Rule::unique('users', 'email')->ignore($this->user()->id)], 'currentPassword' => ['sometimes', 'string', 'max:128']]; }
}
