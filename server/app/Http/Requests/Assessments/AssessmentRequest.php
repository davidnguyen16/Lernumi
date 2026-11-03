<?php

namespace App\Http\Requests\Assessments;

use App\Services\AccessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AccessService::class)->can($this->user(), $this->isMethod('POST') ? 'assessment.create' : 'assessment.update', $this->route('offering'));
    }

    public function rules(): array
    {
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        return [
            'name' => [$presence, 'filled', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'weight' => [$presence, 'numeric', 'between:0,100', 'decimal:0,2'],
            'hurdle' => ['sometimes', 'boolean'], 'isGroup' => ['sometimes', 'boolean'],
            'publishAt' => ['sometimes', 'nullable', 'date'], 'dueAt' => ['sometimes', 'nullable', 'date'],
            'expectedDue' => ['sometimes', 'nullable', 'string', 'max:500'],
            'allowedToolIds' => ['sometimes', 'array', 'max:100'],
            'allowedToolIds.*' => ['required', 'uuid', 'distinct', 'exists:tools,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) return;
            $assessment = $this->route('assessment');
            $partial = $this->isMethod('PATCH');
            $publish = $this->input('publishAt', $partial ? $assessment?->publish_at : null);
            $due = $this->input('dueAt', $partial ? $assessment?->due_at : null);
            if ($publish && $due && CarbonImmutable::parse($due)->lessThan(CarbonImmutable::parse($publish)))
                $validator->errors()->add('dueAt', 'The due time must be at or after the publication time.');
        }];
    }

    public function attributesForModel(): array
    {
        $attributes = [];
        foreach (['name' => 'name', 'description' => 'description', 'weight' => 'weight', 'hurdle' => 'hurdle',
            'isGroup' => 'is_group', 'publishAt' => 'publish_at', 'dueAt' => 'due_at', 'expectedDue' => 'expected_due'] as $field => $column)
            if (array_key_exists($field, $this->validated())) $attributes[$column] = $this->validated($field);
        if ($this->isMethod('PUT')) $attributes += ['description' => null, 'hurdle' => false, 'is_group' => false, 'publish_at' => null, 'due_at' => null, 'expected_due' => null];
        return $attributes;
    }
}
