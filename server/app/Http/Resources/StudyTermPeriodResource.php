<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudyTermPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'studyTermId' => $this->study_term_id, 'name' => $this->name, 'type' => $this->type,
            'startAt' => $this->start_at->toISOString(), 'endAt' => $this->end_at->toISOString(),
            'createdAt' => $this->created_at->toISOString(), 'updatedAt' => $this->updated_at->toISOString()];
    }
}
