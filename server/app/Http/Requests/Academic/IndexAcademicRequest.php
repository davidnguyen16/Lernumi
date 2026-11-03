<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAcademicRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if ($this->has('perPage') && ! $this->has('per_page')) $this->merge(['per_page' => $this->input('perPage')]);
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'institutionId' => ['sometimes', 'uuid'],
            'subjectId' => ['sometimes', 'uuid'],
            'termId' => ['sometimes', 'uuid'],
            'offeringId' => ['sometimes', 'uuid'],
            'year' => ['sometimes', 'integer', 'between:1000,9999'],
            'session' => ['sometimes', 'integer', 'between:1,99'],
            'type' => ['sometimes', Rule::in(['TEACHING', 'BREAK', 'EXAM'])],
            'sort' => ['sometimes', Rule::in(['name', '-name', 'createdAt', '-createdAt', 'year', '-year', 'subjectCode', '-subjectCode', 'publishAt', '-publishAt'])],
        ];
    }
}
