<?php

namespace App\Http\Requests\Institutions;

use App\Services\AccessService;
use Illuminate\Foundation\Http\FormRequest;

abstract class InstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $access = app(AccessService::class);
        return $this->route('institution')
            ? $access->can($this->user(), 'institution.settings.update', $this->route('institution'))
            : $access->isAdmin($this->user());
    }

    protected function institutionRules(bool $partial): array
    {
        return ['name' => [$partial ? 'sometimes' : 'required', 'filled', 'string', 'max:200'],
            'website' => ['sometimes', 'nullable', 'url:http,https', 'max:2048']];
    }

    public function attributesForModel(): array
    {
        $attributes = $this->safe()->only(['name', 'website']);
        if ($this->isMethod('PUT')) $attributes['website'] ??= null;
        return $attributes;
    }
}
