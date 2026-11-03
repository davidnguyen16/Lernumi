<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAccessRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['search' => ['sometimes', 'string', 'max:200'], 'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'], 'userId' => ['sometimes', 'uuid'], 'roleId' => ['sometimes', 'uuid'], 'offeringId' => ['sometimes', 'uuid'], 'status' => ['sometimes', Rule::in(['PENDING', 'APPROVED', 'REJECTED'])], 'includeRevoked' => ['sometimes', 'boolean']]; }
}
