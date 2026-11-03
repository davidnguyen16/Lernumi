<?php

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

class StartAttemptRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['assessmentId' => ['required', 'uuid'], 'groupId' => ['sometimes', 'nullable', 'uuid']];
    }
}
