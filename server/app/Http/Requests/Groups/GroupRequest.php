<?php

namespace App\Http\Requests\Groups;

use Illuminate\Foundation\Http\FormRequest;

class GroupRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) $this->merge(['name' => trim($this->input('name'))]);
    }
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('PATCH') ? 'sometimes' : 'required', 'string', 'max:120'],
            'memberIds' => ['sometimes', 'array', 'min:1', 'max:100'],
            'memberIds.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
