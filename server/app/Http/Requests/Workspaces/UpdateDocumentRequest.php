<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:255'], 'parentFolderId' => ['sometimes', 'nullable', 'uuid']];
    }
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->exists('name') && ! $this->exists('parentFolderId')) {
                $validator->errors()->add('name', 'Provide a name or parentFolderId.');
            }
        }];
    }
}
