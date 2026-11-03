<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'institutionId' => $this->institution_id, 'subjectId' => $this->subject_id, 'termId' => $this->term_id,
            'institution' => new InstitutionResource($this->whenLoaded('institution')),
            'subject' => new SubjectResource($this->whenLoaded('subject')), 'term' => new StudyTermResource($this->whenLoaded('term')),
            'assessmentCount' => $this->whenCounted('assessments'),
            'createdAt' => $this->created_at->toISOString(), 'updatedAt' => $this->updated_at->toISOString()];
    }
}
