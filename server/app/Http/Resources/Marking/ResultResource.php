<?php

namespace App\Http\Resources\Marking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'attemptId' => $this->attempt_id, 'markId' => $this->mark_id,
            'finalisedById' => $this->finalised_by_id, 'finalisedAt' => $this->finalised_at->toISOString(),
            'note' => $this->note, 'mark' => new MarkResource($this->mark),
        ];
    }
}
