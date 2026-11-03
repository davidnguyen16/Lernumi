<?php

namespace App\Http\Resources\Learning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $workspace = $this->workspace?->workspace;
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'assessmentId' => $this->assessment_id,
            'groupId' => $this->group_id,
            'startAt' => $this->start_at->toISOString(),
            'submitAt' => $this->submit_at?->toISOString(),
            'workspaceId' => $workspace?->id,
            'workspace' => $workspace ? ['id' => $workspace->id, 'name' => $workspace->name, 'lastOpenedAt' => $workspace->last_opened_at?->toISOString()] : null,
            'group' => $this->whenLoaded('group', fn () => $this->group ? new GroupResource($this->group) : null),
        ];
    }
}
