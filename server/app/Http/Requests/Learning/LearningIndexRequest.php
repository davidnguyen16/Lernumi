<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class LearningIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'roleAssignmentId' => ['sometimes', 'nullable', 'uuid'],
            'institutionId' => ['sometimes', 'nullable', 'uuid'],
            'termId' => ['sometimes', 'nullable', 'uuid'],
            'subjectId' => ['sometimes', 'nullable', 'uuid'],
            'assessmentId' => ['sometimes', 'nullable', 'uuid'],
            'studentId' => ['sometimes', 'nullable', 'uuid'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
