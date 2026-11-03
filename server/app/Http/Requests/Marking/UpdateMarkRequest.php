<?php

namespace App\Http\Requests\Marking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMarkRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'score' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
            'outOf' => ['sometimes', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000000'],
            'feedback' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(['score', 'outOf', 'feedback'])) {
                $validator->errors()->add('score', 'Provide a score, scale, or feedback.');
            }
        }];
    }
}
