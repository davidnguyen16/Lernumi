<?php

namespace App\Http\Requests\Offerings;

use App\Services\AccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AccessService::class)->can($this->user(), $this->isMethod('POST') ? 'offering.create' : 'offering.update', $this->route('offering') ?? $this->route('institution'));
    }

    public function rules(): array
    {
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $institutionId = $this->route('institution')->id;
        return [
            'subjectId' => [$presence, 'uuid', Rule::exists('subjects', 'id')->where('institution_id', $institutionId)],
            'termId' => [$presence, 'uuid', Rule::exists('study_terms', 'id')->where('institution_id', $institutionId)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) return;
            $offering = $this->route('offering');
            $duplicate = $this->route('institution')->offerings()
                ->where('subject_id', $this->input('subjectId', $offering?->subject_id))
                ->where('term_id', $this->input('termId', $offering?->term_id))
                ->when($offering, fn ($query) => $query->whereKeyNot($offering->id))->exists();
            if ($duplicate) $validator->errors()->add('subjectId', 'This subject is already offered in this term.');
        }];
    }

    public function attributesForModel(): array
    {
        $attributes = [];
        foreach (['subjectId' => 'subject_id', 'termId' => 'term_id'] as $field => $column)
            if (array_key_exists($field, $this->validated())) $attributes[$column] = $this->validated($field);
        return $attributes;
    }
}
