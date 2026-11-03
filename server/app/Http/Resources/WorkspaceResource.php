<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attempt = $this->assignment?->attempt;

        return [
            'id' => $this->id, 'name' => $this->name,
            'kind' => $this->personal ? 'PERSONAL' : 'ASSIGNMENT',
            'documentCount' => $this->documents_count ?? $this->documents()->count(),
            'createdAt' => $this->created_at->toISOString(), 'updatedAt' => $this->updated_at->toISOString(),
            'lastOpenedAt' => $this->last_opened_at?->toISOString(),
            'submitted' => $attempt?->submit_at !== null,
            'readOnly' => $attempt !== null && (! $attempt->isOwnedBy($request->user()) || $attempt->submit_at !== null),
            'attempt' => $attempt ? [
                'id' => $attempt->id, 'assessmentId' => $attempt->assessment_id,
                'assessmentName' => $attempt->assessment->name,
                'submitAt' => $attempt->submit_at?->toISOString(),
                'offeringId' => $attempt->assessment->offering_id,
            ] : null,
        ];
    }
}
