<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ToolVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'toolId' => $this->tool_id, 'version' => $this->version,
            'entryPath' => $this->entry_path, 'manifest' => $this->manifest,
            'sha256' => $this->sha256, 'compressedSize' => $this->compressed_size,
            'unpackedSize' => $this->unpacked_size, 'fileCount' => $this->file_count,
            'releaseNotes' => $this->release_notes, 'createdById' => $this->created_by_id,
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
