<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('file') && ! $this->filled('name')) {
            $this->merge(['name' => $this->file('file')->getClientOriginalName()]);
        }
        if (! $this->exists('entryKind')) {
            $this->merge(['entryKind' => 'FILE']);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'entryKind' => ['required', Rule::in(['FILE', 'FOLDER'])],
            'parentFolderId' => ['sometimes', 'nullable', 'uuid'],
            'file' => ['sometimes', 'prohibited_if:entryKind,FOLDER', 'file', 'max:20480'],
            'content' => ['sometimes', 'prohibited_if:entryKind,FOLDER', Rule::prohibitedIf(fn () => $this->hasFile('file')), 'nullable', 'string'],
            'reuseExisting' => ['sometimes', 'boolean'],
        ];
    }
}
