<?php

namespace App\Http\Requests\Marking;

use Illuminate\Foundation\Http\FormRequest;

class FinaliseResultRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['markId' => ['required', 'uuid'], 'note' => ['sometimes', 'nullable', 'string', 'max:10000']];
    }
}
