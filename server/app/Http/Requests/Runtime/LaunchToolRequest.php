<?php

namespace App\Http\Requests\Runtime;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaunchToolRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->is_active === true; }

    public function rules(): array
    {
        return ['mode' => ['required', Rule::in(['WORK', 'PERSONAL', 'REPLAY'])],
            'attemptId' => ['required_if:mode,WORK', 'prohibited_unless:mode,WORK', 'uuid'],
            'workspaceId' => ['required_if:mode,PERSONAL', 'prohibited_unless:mode,PERSONAL', 'uuid'],
            'toolId' => ['required_unless:mode,REPLAY', 'prohibited_if:mode,REPLAY', 'uuid'],
            'workSessionId' => ['required_if:mode,REPLAY', 'prohibited_unless:mode,REPLAY', 'uuid'],
            'roleAssignmentId' => ['sometimes', 'nullable', 'uuid']];
    }
}
