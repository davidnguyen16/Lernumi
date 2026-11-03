<?php

namespace App\Http\Requests\Terms;

use App\Services\AccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StudyTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AccessService::class)->can($this->user(), $this->isMethod('POST') ? 'term.create' : 'term.update', $this->route('institution'));
    }

    public function rules(): array
    {
        $partial = $this->isMethod('PATCH');
        return [
            'year' => [$partial ? 'sometimes' : 'required', 'integer', 'between:1000,9999'],
            'session' => [$partial ? 'sometimes' : 'required', 'integer', 'between:1,99'],
            'periods' => ['sometimes', 'array', 'max:100'],
            'periods.*.name' => ['required', 'string', 'max:120'],
            'periods.*.type' => ['required', Rule::in(['TEACHING', 'BREAK', 'EXAM'])],
            'periods.*.startAt' => ['required', 'date'],
            'periods.*.endAt' => ['required', 'date', 'after:periods.*.startAt'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) return;
            $term = $this->route('term');
            $duplicate = $this->route('institution')->terms()
                ->where('year', $this->input('year', $term?->year))
                ->where('session', $this->input('session', $term?->session))
                ->when($term, fn ($query) => $query->whereKeyNot($term->id))->exists();
            if ($duplicate) $validator->errors()->add('session', 'This year and session already exist in this institution.');
        }];
    }
}
