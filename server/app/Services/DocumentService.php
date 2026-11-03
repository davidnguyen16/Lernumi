<?php

namespace App\Services;

use App\Exceptions\ToolApiException;
use App\Models\Document;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    public function create(Workspace $workspace, array $data, string $content = '', ?string $mime = null): Document
    {
        $this->validateName($data['name']);
        if ($data['entryKind'] === 'FOLDER' && $content !== '') {
            throw ValidationException::withMessages(['content' => 'Folders cannot contain file content.']);
        }
        $this->validateSize($content);
        $mime = $this->normalizeMime($mime);
        try {
            return DB::transaction(function () use ($workspace, $data, $content, $mime): Document {
                $this->lockEditableWorkspace($workspace);
                $parent = $data['parentFolderId'] ?? null;
                $this->validateParent($workspace, $parent);
                $existing = Document::query()->where('workspace_id', $workspace->id)->where('parent_folder_id', $parent)->where('name', $data['name'])->first();
                if ($existing && ($data['reuseExisting'] ?? false) && $existing->entry_kind === 'FOLDER' && $data['entryKind'] === 'FOLDER') {
                    return $existing;
                }
                if ($existing) {
                    throw ValidationException::withMessages(['name' => 'An entry with this name already exists in this folder.']);
                }
                $attributes = [
                    'workspace_id' => $workspace->id, 'name' => $data['name'],
                    'parent_folder_id' => $parent, 'entry_kind' => $data['entryKind'],
                ];
                if ($data['entryKind'] === 'FILE') {
                    $id = (string) Str::uuid();
                    $hash = hash('sha256', $content);
                    $key = "documents/{$workspace->id}/{$id}/{$hash}";
                    $this->write($key, $content);
                    $document = new Document($attributes + ['content_key' => $key, 'content_hash' => $hash, 'mime_type' => $mime ?: 'text/plain', 'size_bytes' => strlen($content)]);
                    $document->id = $id;
                    $document->save();

                    return $document;
                }

                return Document::query()->create($attributes);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['name' => 'An entry with this name already exists in this folder.']);
        }
    }

    public function update(Document $document, array $data): Document
    {
        try {
            return DB::transaction(function () use ($document, $data): Document {
                $this->lockEditableWorkspace($document->workspace);
                $document = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                if (array_key_exists('name', $data)) {
                    $this->validateName($data['name']);
                    $document->name = $data['name'];
                }
                if (array_key_exists('parentFolderId', $data)) {
                    $parent = $data['parentFolderId'];
                    $this->validateParent($document->workspace, $parent);
                    for ($cursor = $parent; $cursor !== null;) {
                        if ($cursor === $document->id) {
                            throw ValidationException::withMessages(['parentFolderId' => 'A folder cannot be moved into itself or its descendants.']);
                        }
                        $cursor = Document::query()->whereKey($cursor)->value('parent_folder_id');
                    }
                    $document->parent_folder_id = $parent;
                }
                $document->save();

                return $document;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['name' => 'An entry with this name already exists in this folder.']);
        }
    }

    public function overwrite(Document $document, string $content, ?string $ifMatch, ?string $mime = null): Document
    {
        $this->validateSize($content);
        $mime = $this->normalizeMime($mime);
        if ($ifMatch === null) {
            throw new ToolApiException('PRECONDITION_REQUIRED', 'Supply the current ETag in If-Match.', 428);
        }

        return DB::transaction(function () use ($document, $content, $ifMatch, $mime): Document {
            $this->lockEditableWorkspace($document->workspace);
            $document = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless($document->entry_kind === 'FILE', 422, 'Folders have no content.');
            if (! hash_equals($document->content_hash ?? '', trim($ifMatch, '"'))) {
                throw new ToolApiException('PRECONDITION_FAILED', 'The document has changed. Reload before saving.', 412, ['currentContentHash' => $document->content_hash]);
            }
            $hash = hash('sha256', $content);
            $key = "documents/{$document->workspace_id}/{$document->id}/{$hash}";
            $this->write($key, $content);
            $document->update(['content_key' => $key, 'content_hash' => $hash, 'mime_type' => $mime ?: $document->mime_type, 'size_bytes' => strlen($content)]);

            return $document;
        });
    }

    public function read(Document $document): string
    {
        abort_unless($document->entry_kind === 'FILE', 422, 'Folders have no content.');
        abort_unless($document->content_key && Storage::disk('local')->exists($document->content_key), 404, 'Document content not found.');

        return Storage::disk('local')->get($document->content_key);
    }

    public function delete(Document $document): void
    {
        DB::transaction(function () use ($document): void {
            $this->lockEditableWorkspace($document->workspace);
            $document->delete();
        });
        // Content-addressed objects are retained for recorded save checkpoints and recovery.
    }

    private function validateParent(Workspace $workspace, ?string $parent): void
    {
        if ($parent !== null && ! Document::query()->whereKey($parent)->where('workspace_id', $workspace->id)->where('entry_kind', 'FOLDER')->exists()) {
            throw ValidationException::withMessages(['parentFolderId' => 'Choose a folder in this workspace.']);
        }
    }

    private function lockEditableWorkspace(Workspace $workspace): void
    {
        $assignment = $workspace->assignment;
        if ($assignment) {
            $attempt = \App\Models\AssessmentAttempt::query()->whereKey($assignment->attempt_id)->lockForUpdate()->firstOrFail();
            if ($attempt->submit_at !== null) {
                throw new ToolApiException('WORKSPACE_READ_ONLY', 'Submitted assessment workspaces cannot be modified.', 403);
            }
        }
        Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
    }

    private function validateName(string $name): void
    {
        if ($name === '' || in_array($name, ['.', '..'], true) || preg_match('/[\x00-\x1f\x7f\/\\\\]/u', $name)) {
            throw ValidationException::withMessages(['name' => 'Use a single filename without path separators or control characters.']);
        }
    }

    private function validateSize(string $content): void
    {
        if (strlen($content) > config('tools.max_file_bytes')) {
            throw ValidationException::withMessages(['file' => 'Files must not exceed 20 MiB.']);
        }
    }

    private function normalizeMime(?string $mime): ?string
    {
        if ($mime === null) return null;
        $mime = strtolower(trim(explode(';', $mime, 2)[0]));
        if (strlen($mime) > 150 || ! preg_match('/^[a-z0-9][a-z0-9!#$&^_.+-]*\/[a-z0-9][a-z0-9!#$&^_.+-]*$/iD', $mime)) {
            throw ValidationException::withMessages(['contentType' => 'Supply a valid media type of at most 150 bytes.']);
        }

        return $mime;
    }

    private function write(string $key, string $content): void
    {
        if (! Storage::disk('local')->put($key, $content)) {
            throw new ToolApiException('STORAGE_UNAVAILABLE', 'Could not persist document content.', 503);
        }
    }
}
