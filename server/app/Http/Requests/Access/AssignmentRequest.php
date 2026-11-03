<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class AssignmentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['userId' => ['required', 'uuid', 'exists:users,id'], 'roleId' => ['required', 'uuid'], 'offeringIds' => ['sometimes', 'array', 'max:100'], 'offeringIds.*' => ['required', 'uuid', 'distinct']]; }
}
