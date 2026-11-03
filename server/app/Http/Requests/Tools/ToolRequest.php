<?php

namespace App\Http\Requests\Tools;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToolRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $create = $this->isMethod('POST') && ! $this->route('tool');

        return [
            'code' => [$create ? 'required' : 'prohibited', 'string', 'min:2', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tools', 'code')],
            'name' => [$create ? 'required' : 'sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'codeBaseLocation' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'package' => ['sometimes', 'file', 'max:25600'],
            'releaseNotes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'activate' => ['sometimes', 'boolean'],
        ];
    }
}
