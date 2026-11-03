<?php

namespace App\Http\Resources\Marking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'attemptId' => $this->attempt_id, 'markedById' => $this->marked_by_id,
            'markerName' => $this->marker->name, 'markerEmail' => $this->marker->email,
            'score' => $this->score, 'outOf' => $this->out_of, 'feedback' => $this->feedback,
            'status' => $this->status, 'supersedesMarkId' => $this->supersedes_mark_id,
            'createdAt' => $this->created_at->toISOString(), 'updatedAt' => $this->updated_at->toISOString(),
            'submittedAt' => $this->submitted_at?->toISOString(),
            'official' => $this->attempt->result?->mark_id === $this->id,
            'own' => $this->marked_by_id === $request->user()?->id,
        ];
    }
}
