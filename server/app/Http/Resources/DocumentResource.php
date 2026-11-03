<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'workspaceId' => $this->workspace_id,
            'parentFolderId' => $this->parent_folder_id, 'entryKind' => $this->entry_kind,
            'contentHash' => $this->content_hash, 'mimeType' => $this->mime_type, 'sizeBytes' => $this->size_bytes,
            'createdAt' => $this->created_at->toISOString(), 'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}
