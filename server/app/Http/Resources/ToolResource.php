<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ToolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'description' => $this->description, 'codeBaseLocation' => $this->code_base_location,
            'activeVersionId' => $this->active_version_id,
            'activeVersion' => new ToolVersionResource($this->whenLoaded('activeVersion')),
            'versions' => ToolVersionResource::collection($this->whenLoaded('versions')),
            'createdAt' => $this->created_at->toISOString(), 'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}
