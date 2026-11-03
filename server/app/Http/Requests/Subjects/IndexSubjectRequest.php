<?php

namespace App\Http\Requests\Subjects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSubjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('perPage') && ! $this->has('per_page')) $this->merge(['per_page' => $this->input('perPage')]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', Rule::in(['subjectCode', '-subjectCode', 'name', '-name', 'createdAt', '-createdAt'])],
        ];
    }
}
