<?php

namespace App\Http\Requests\Groups;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) $this->merge(['name' => trim($this->input('name'))]);
        if (is_array($this->input('memberEmails'))) {
            $this->merge(['memberEmails' => array_map(fn ($email) => is_string($email) ? strtolower(trim($email)) : $email, $this->input('memberEmails'))]);
        }
    }
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('PATCH') ? 'sometimes' : 'required', 'string', 'max:120'],
            'memberIds' => ['sometimes', Rule::prohibitedIf(fn () => $this->exists('memberEmails')), 'array', 'min:1', 'max:100'],
            'memberIds.*' => ['required', 'uuid', 'distinct'],
            'memberEmails' => ['sometimes', Rule::prohibitedIf(fn () => $this->exists('memberIds')), 'array', 'max:100'],
            'memberEmails.*' => ['required', 'string', 'email:rfc', 'max:254', 'distinct'],
        ];
    }
}
