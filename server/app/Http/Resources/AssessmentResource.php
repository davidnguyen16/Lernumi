<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'offeringId' => $this->offering_id, 'name' => $this->name, 'description' => $this->description,
            'weight' => $this->weight, 'hurdle' => $this->hurdle, 'isGroup' => $this->is_group,
            'publishAt' => $this->publish_at?->toISOString(), 'dueAt' => $this->due_at?->toISOString(), 'expectedDue' => $this->expected_due,
            'allowedTools' => $this->whenLoaded('allowedTools', fn () => $this->allowedTools->map(fn ($tool) => [
                'id' => $tool->id, 'code' => $tool->code, 'name' => $tool->name, 'description' => $tool->description,
            ])),
            'attemptCount' => $this->whenCounted('attempts'),
            'createdAt' => $this->created_at->toISOString(), 'updatedAt' => $this->updated_at->toISOString()];
    }
}
