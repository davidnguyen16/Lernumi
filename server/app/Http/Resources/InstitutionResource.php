<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstitutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'website' => $this->website,
            'logoUrl' => $this->logo_key ? url('/api/v1/institutions/'.$this->id.'/logo') : null,
            'counts' => $this->when(isset($this->subjects_count), fn () => [
                'subjects' => (int) $this->subjects_count, 'terms' => (int) $this->terms_count,
                'roles' => (int) $this->roles_count,
                'requests' => (int) $this->role_requests_count + (int) $this->scope_requests_count,
            ]),
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}
