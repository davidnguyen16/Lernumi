<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        if ($this->has('passwordConfirmation')) $this->merge(['password_confirmation' => $this->input('passwordConfirmation')]);
        $this->merge(['email' => is_string($this->email) ? strtolower(trim($this->email)) : $this->email]);
        if (! $this->filled('name') && ($this->filled('firstName') || $this->filled('lastName'))) $this->merge(['name' => trim($this->input('firstName', '').' '.$this->input('lastName', ''))]);
    }
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:200'], 'firstName' => ['sometimes', 'nullable', 'string', 'max:100'], 'lastName' => ['sometimes', 'nullable', 'string', 'max:100'], 'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'], 'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers(), new \App\Rules\PasswordByteLength]];
    }
}
