<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspaces\StoreDocumentRequest;
use App\Http\Requests\Workspaces\UpdateDocumentRequest;
use App\Http\Requests\Workspaces\WorkspaceRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\Document;
use App\Models\Tool;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\DocumentService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkspaceController
{
    public function __construct(private WorkspaceService $workspaces, private DocumentService $documents, private AccessService $access) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100'], 'search' => ['sometimes', 'nullable', 'string', 'max:120']]);
        $user = $request->user();
        $offeringIds = $this->access->offeringIds($user, 'attempt.view_own');
        $query = Workspace::query()->with(['personal', 'assignment.attempt.assessment.offering.subject'])->withCount('documents')
            ->where(function ($query) use ($user, $offeringIds): void {
                $query->whereHas('personal', fn ($query) => $query->where('user_id', $user->id))
                    ->orWhereHas('assignment.attempt', function ($query) use ($user, $offeringIds): void {
                        $query->whereHas('assessment', fn ($query) => $query->whereIn('offering_id', $offeringIds))
                            ->where(function ($query) use ($user): void {
                                $query->where('user_id', $user->id)->orWhereHas('group.members', fn ($query) => $query->where('user_id', $user->id));
                            });
                    });
            });
        if (($filters['search'] ?? '') !== '' && ($filters['search'] ?? null) !== null) {
            $query->whereRaw('strpos(lower(name), lower(?)) > 0', [$filters['search']]);
        }

        return WorkspaceResource::collection($query->orderByDesc('last_opened_at')->orderBy('id')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString());
    }

    public function store(WorkspaceRequest $request)
    {
        return (new WorkspaceResource($this->workspaces->createPersonal($request->user(), $request->validated('name'))))->response()->setStatusCode(201);
    }

    public function show(Request $request, Workspace $workspace)
    {
        $this->workspaces->authorize($request->user(), $workspace);
        $request->validate(['folder' => ['sometimes', 'nullable', 'uuid']]);
        $folderId = $request->query('folder');
        if ($folderId !== null) {
            Document::query()->where('workspace_id', $workspace->id)->whereKey($folderId)->where('entry_kind', 'FOLDER')->firstOrFail();
        }
        $workspace->update(['last_opened_at' => now()]);
        $breadcrumbs = [];
        for ($cursor = $folderId; $cursor !== null;) {
            $folder = Document::query()->where('workspace_id', $workspace->id)->findOrFail($cursor);
            array_unshift($breadcrumbs, ['id' => $folder->id, 'name' => $folder->name]);
            $cursor = $folder->parent_folder_id;
        }
        $tools = Tool::query()->with('activeVersion')->whereNotNull('active_version_id');
        if ($workspace->assignment) {
            $tools->whereIn('id', DB::table('assessment_allow_tools')->where('assessment_id', $workspace->assignment->attempt->assessment_id)->select('tool_id'));
        }

        return response()->json([
            'data' => (new WorkspaceResource($workspace))->resolve($request),
            'currentFolderId' => $folderId, 'breadcrumbs' => $breadcrumbs,
            'documents' => DocumentResource::collection($workspace->documents()->where('parent_folder_id', $folderId)->orderBy('entry_kind')->orderBy('name')->get())->resolve($request),
            'tools' => $tools->get()->map(fn (Tool $tool) => ['id' => $tool->id, 'code' => $tool->code, 'name' => $tool->name, 'description' => $tool->description, 'activeVersionId' => $tool->active_version_id]),
        ]);
    }

    public function update(WorkspaceRequest $request, Workspace $workspace)
    {
        $this->workspaces->authorize($request->user(), $workspace, true);
        abort_unless($workspace->personal, 403, 'Only personal workspaces can be renamed.');
        $workspace->update(['name' => $request->validated('name')]);

        return new WorkspaceResource($workspace);
    }

    public function destroy(Request $request, Workspace $workspace)
    {
        $this->workspaces->authorize($request->user(), $workspace, true);
        abort_unless($workspace->personal, 403, 'Assessment workspaces are retained with their attempts.');
        $workspace->delete();

        return response()->noContent();
    }

    public function indexDocuments(Request $request, Workspace $workspace)
    {
        $this->workspaces->authorize($request->user(), $workspace);
        $request->validate(['parentFolderId' => ['sometimes', 'nullable', 'string'], 'cursor' => ['sometimes', 'uuid'], 'limit' => ['sometimes', 'integer', 'between:1,200']]);
        $query = $workspace->documents()->orderBy('id');
        if ($request->exists('parentFolderId')) {
            $parent = $request->query('parentFolderId');
            if ($parent !== 'root' && $parent !== null && ! \Illuminate\Support\Str::isUuid($parent)) {
                throw ValidationException::withMessages(['parentFolderId' => 'Choose root or a folder UUID.']);
            }
            if ($parent !== 'root' && $parent !== null && ! $workspace->documents()->whereKey($parent)->where('entry_kind', 'FOLDER')->exists()) {
                throw ValidationException::withMessages(['parentFolderId' => 'Choose a folder in this workspace.']);
            }
            $query->where('parent_folder_id', $parent === 'root' ? null : $parent);
        }
        if ($request->filled('cursor')) {
            $query->where('id', '>', $request->query('cursor'));
        }
        $limit = (int) $request->query('limit', 200);
        $rows = $query->limit($limit + 1)->get();

        return response()->json(['documents' => DocumentResource::collection($rows->take($limit))->resolve($request), 'nextCursor' => $rows->count() > $limit ? $rows[$limit - 1]->id : null]);
    }

    public function storeDocument(StoreDocumentRequest $request, Workspace $workspace)
    {
        $this->workspaces->authorize($request->user(), $workspace, true);
        $file = $request->file('file');
        $document = $this->documents->create($workspace, $request->validated(), $file ? $file->getContent() : ($request->validated('content') ?? ''), $file?->getMimeType());

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    public function showDocument(Request $request, Workspace $workspace, Document $document)
    {
        $this->documentAccess($request, $workspace, $document);
        $bytes = $this->documents->read($document);

        return response($bytes)->header('Content-Type', 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="'.addcslashes($document->name, '"\\').'"')
            ->header('ETag', '"'.$document->content_hash.'"')->header('X-Content-Type-Options', 'nosniff');
    }

    public function updateDocument(UpdateDocumentRequest $request, Workspace $workspace, Document $document)
    {
        $this->documentAccess($request, $workspace, $document, true);

        return new DocumentResource($this->documents->update($document, $request->validated()));
    }

    public function updateContent(Request $request, Workspace $workspace, Document $document)
    {
        $this->documentAccess($request, $workspace, $document, true);
        $document = $this->documents->overwrite($document, $request->getContent(), $request->header('If-Match'), $request->header('Content-Type'));

        return (new DocumentResource($document))->response()->header('ETag', '"'.$document->content_hash.'"');
    }

    public function destroyDocument(Request $request, Workspace $workspace, Document $document)
    {
        $this->documentAccess($request, $workspace, $document, true);
        $this->documents->delete($document);

        return response()->noContent();
    }

    public function destroyDocuments(Request $request, Workspace $workspace)
    {
        $this->workspaces->authorize($request->user(), $workspace, true);
        $data = $request->validate(['documentIds' => ['required', 'array', 'min:1', 'max:500'], 'documentIds.*' => ['required', 'uuid', 'distinct']]);
        DB::transaction(function () use ($workspace, $data): void {
            $documents = $workspace->documents()->whereIn('id', $data['documentIds'])->get();
            abort_unless($documents->count() === count($data['documentIds']), 404);
            foreach ($documents as $document) {
                $this->documents->delete($document);
            }
        });

        return response()->noContent();
    }

    private function documentAccess(Request $request, Workspace $workspace, Document $document, bool $write = false): void
    {
        abort_unless($document->workspace_id === $workspace->id, 404);
        $this->workspaces->authorize($request->user(), $workspace, $write);
    }
}
