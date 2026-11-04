<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudyTermResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'institutionId' => $this->institution_id, 'year' => $this->year, 'session' => $this->session,
            'periods' => StudyTermPeriodResource::collection($this->whenLoaded('periods')),
            'offeringCount' => $this->whenCounted('offerings'),
            'createdAt' => $this->created_at->toISOString(), 'updatedAt' => $this->updated_at->toISOString()];
    }
}
