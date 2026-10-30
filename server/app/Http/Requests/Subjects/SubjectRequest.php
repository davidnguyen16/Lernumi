<?php

namespace App\Http\Requests\Subjects;

use App\Models\Institution;
use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication and institution RBAC are separate migration work.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('subjectCode'))) {
            $this->merge(['subjectCode' => strtoupper(trim($this->input('subjectCode')))]);
        }
    }

    protected function subjectRules(bool $partial): array
    {
        /** @var Institution $institution */
        $institution = $this->route('institution');
        $uniqueCode = Rule::unique('subjects', 'subject_code')
            ->where('institution_id', $institution->getKey());

        if ($this->route('subject') instanceof Subject) {
            $uniqueCode->ignore($this->route('subject'));
        }

        return [
            'subjectCode' => [$partial ? 'sometimes' : 'required', 'filled', 'string', 'min:2', 'max:20', 'regex:/^[A-Z0-9-]+$/', $uniqueCode],
            'subjectName' => [$partial ? 'sometimes' : 'required', 'filled', 'string', 'max:200'],
            'subjectDescription' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /** Map only validated fields; an institution cannot be changed through the body. */
    public function subjectAttributes(): array
    {
        $attributes = [];

        foreach ([
            'subjectCode' => 'subject_code',
            'subjectName' => 'subject_name',
            'subjectDescription' => 'subject_description',
        ] as $field => $column) {
            if (array_key_exists($field, $this->validated())) {
                $attributes[$column] = $this->validated($field);
            }
        }

        return $attributes;
    }
}
