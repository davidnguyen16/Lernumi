<?php

namespace App\Http\Resources\Learning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'assessmentId' => $this->assessment_id,
            'name' => $this->name, 'createdById' => $this->created_by_id,
            'locked' => $this->attempts()->exists(),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($member) => [
                'id' => $member->id, 'userId' => $member->user_id,
                'displayName' => $member->user->name, 'email' => $member->user->email,
            ])),
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}
