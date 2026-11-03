<?php

namespace App\Http\Controllers;

use App\Exceptions\ToolApiException;
use App\Http\Requests\Runtime\EventBatchRequest;
use App\Http\Requests\Runtime\LaunchToolRequest;
use App\Http\Requests\Workspaces\StoreDocumentRequest;
use App\Http\Requests\Workspaces\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\AssignmentWorkSession;
use App\Models\Document;
use App\Models\RoleAssignment;
use App\Models\ToolRuntimeSession;
use App\Services\DocumentService;
use App\Services\ToolRuntimeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class ToolRuntimeController
{
    public function __construct(private readonly ToolRuntimeService $runtime, private readonly DocumentService $documents) {}

    public function launch(LaunchToolRequest $request)
    {
        return response()->json($this->runtime->launch($request, $request->validated()), 201)->header('Cache-Control', 'no-store');
    }

    public function token(Request $request)
    {
        $session = $this->session($request);
        $grant = $this->runtime->refresh($session);
        $response = response()->json($grant);
        if (strtolower($request->getSchemeAndHttpHost()) === strtolower($this->runtime->origins()))
            $response->withCookie($this->runtimeCookie($session, $grant['token']));
        return $response;
    }
    public function close(Request $request) { return response()->json($this->runtime->close($this->session($request))); }

    public function runtimeAuth(Request $request)
    {
        $origin = $this->runtime->origins();
        if (strtolower($request->getSchemeAndHttpHost()) !== strtolower($origin))
            throw new ToolApiException('AUTHORIZATION_FORBIDDEN', 'Runtime authentication is only available on the tool origin.', 403);
        $session = $this->session($request);
        $session->loadMissing('tool', 'toolVersion');
        $path = '/tools/'.rawurlencode($session->tool->code).'/'.$session->tool_version_id;
        $entry = implode('/', array_map('rawurlencode', explode('/', $session->toolVersion->entry_path)));
        return response()->json(['runtimePath' => $path.'/'.$entry])->withCookie($this->runtimeCookie($session, $request->bearerToken()));
    }

    public function context(Request $request)
    {
        $session = $this->session($request);
        $session->loadMissing('tool', 'toolVersion', 'workspace.personal', 'workspace.assignment.attempt.user', 'workspace.assignment.attempt.assessment.offering.subject.institution', 'workspace.assignment.attempt.assessment.offering.term', 'workSession');
        $workspace = $session->workspace;
        $person = fn ($user) => ['id' => $user->id, 'displayName' => $user->name, 'avatarUrl' => null];
        $assignment = $session->role_assignment_id ? RoleAssignment::query()->with('role.institution')->find($session->role_assignment_id) : null;
        $data = ['apiVersion' => 'v1', 'platform' => ['dashboardUrl' => rtrim(config('tools.platform_origin'), '/').'/navigation/dashboard'],
            'user' => $person($session->user), 'role' => $assignment ? ['id' => $assignment->role->id, 'roleCode' => $assignment->role->role_code, 'roleName' => $assignment->role->role_name, 'scopeType' => $assignment->role->scope_type] : null,
            'tool' => ['id' => $session->tool_id, 'code' => $session->tool->code, 'name' => $session->tool->name, 'description' => $session->tool->description,
                'version' => ['id' => $session->tool_version_id, 'version' => $session->toolVersion->version, 'entry' => $session->toolVersion->entry_path]],
            'session' => ['id' => $session->work_session_id ?? $session->id, 'runtimeSessionId' => $session->id, 'mode' => $session->mode, 'readOnly' => $session->read_only,
                'capabilities' => $session->capabilities, 'startedAt' => ($session->workSession?->start_at ?? $session->created_at)->toISOString(), 'endedAt' => $session->workSession?->end_at?->toISOString()],
            'workspace' => ['id' => $workspace->id, 'name' => $workspace->name, 'kind' => $workspace->personal ? 'PERSONAL' : 'ASSIGNMENT',
                'createdAt' => $workspace->created_at->toISOString(), 'lastOpenedAt' => $workspace->last_opened_at?->toISOString()]];
        if ($workspace->personal) {
            $data['attempt'] = ['id' => $workspace->id, 'startedAt' => $session->created_at->toISOString(), 'submittedAt' => null, 'owner' => $person($session->user)];
            $data['assessment'] = ['id' => $workspace->id, 'name' => $workspace->name, 'description' => 'Personal workspace', 'weight' => 0, 'hurdle' => false, 'isGroup' => false, 'publishAt' => null, 'dueAt' => null, 'expectedDue' => null];
            $data['offering'] = ['id' => $workspace->id, 'institution' => $assignment ? ['id' => $assignment->role->institution_id, 'name' => $assignment->role->institution->name] : null,
                'subject' => ['code' => 'PERSONAL', 'name' => 'Personal workspace'], 'term' => ['id' => $workspace->id, 'year' => now()->year, 'session' => 0]];
        } else {
            $attempt = $workspace->assignment->attempt; $assessment = $attempt->assessment; $offering = $assessment->offering;
            $data['attempt'] = ['id' => $attempt->id, 'startedAt' => $attempt->start_at->toISOString(), 'submittedAt' => $attempt->submit_at?->toISOString(), 'owner' => $person($attempt->user)];
            $data['assessment'] = ['id' => $assessment->id, 'name' => $assessment->name, 'description' => $assessment->description, 'weight' => $assessment->weight,
                'hurdle' => $assessment->hurdle, 'isGroup' => $assessment->is_group, 'publishAt' => $assessment->publish_at?->toISOString(), 'dueAt' => $assessment->due_at?->toISOString(), 'expectedDue' => $assessment->expected_due];
            $data['offering'] = ['id' => $offering->id, 'institution' => ['id' => $offering->institution_id, 'name' => $offering->subject->institution->name],
                'subject' => ['code' => $offering->subject->subject_code, 'name' => $offering->subject->subject_name], 'term' => ['id' => $offering->term_id, 'year' => $offering->term->year, 'session' => $offering->term->session]];
        }
        return response()->json($data);
    }

    public function documents(Request $request)
    {
        $request->validate(['parentFolderId' => ['sometimes', 'nullable', 'string'], 'cursor' => ['sometimes', 'uuid'], 'limit' => ['sometimes', 'integer', 'between:1,200']]);
        $session = $this->session($request);
        $parent = $request->input('parentFolderId');
        if ($parent === 'root' || $parent === '') $parent = null;
        if ($parent !== null && (! Str::isUuid($parent) || ! $session->workspace->documents()->whereKey($parent)->where('entry_kind', 'FOLDER')->exists()))
            throw new ToolApiException('NOT_FOUND', 'Parent folder not found.', 404);
        $query = $session->workspace->documents()->where('parent_folder_id', $parent)->getQuery();
        $cursor = $request->input('cursor');
        if ($cursor) {
            $after = (clone $query)->whereKey($cursor)->first();
            if (! $after) throw new ToolApiException('NOT_FOUND', 'Cursor document not found in this folder.', 404);
            $query->where(fn (Builder $q) => $q->where('entry_kind', '<', $after->entry_kind)
                ->orWhere(fn (Builder $q) => $q->where('entry_kind', $after->entry_kind)->where('name', '>', $after->name))
                ->orWhere(fn (Builder $q) => $q->where('entry_kind', $after->entry_kind)->where('name', $after->name)->where('id', '>', $after->id)));
        }
        $limit = (int) $request->input('limit', 100);
        $rows = $query->orderByDesc('entry_kind')->orderBy('name')->orderBy('id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit; $page = $rows->take($limit);
        return response()->json(['documents' => DocumentResource::collection($page)->resolve($request), 'nextCursor' => $hasMore ? $page->last()->id : null]);
    }

    public function storeDocument(StoreDocumentRequest $request)
    {
        $session = $this->session($request); $file = $request->file('file');
        if ($file && $request->validated('entryKind') !== 'FILE') throw new ToolApiException('VALIDATION_ERROR', 'Files cannot be uploaded as folders.', 400);
        $document = $this->documents->create($session->workspace, $request->validated(), $file ? $file->getContent() : (string) $request->validated('content', ''), $file?->getMimeType());
        return response()->json(['document' => (new DocumentResource($document))->resolve($request)], 201);
    }

    public function showDocument(Request $request, string $documentId)
    {
        return response()->json(['document' => (new DocumentResource($this->document($request, $documentId)))->resolve($request)]);
    }

    public function updateDocument(UpdateDocumentRequest $request, string $documentId)
    {
        $document = $this->documents->update($this->document($request, $documentId), $request->validated());
        return response()->json(['document' => (new DocumentResource($document))->resolve($request)]);
    }

    public function deleteDocument(Request $request, string $documentId)
    {
        $this->documents->delete($this->document($request, $documentId));
        return response()->noContent();
    }

    public function content(Request $request, string $documentId)
    {
        $document = $this->document($request, $documentId);
        return response($this->documents->read($document))->header('Content-Type', $document->mime_type ?: 'application/octet-stream')
            ->header('ETag', '"'.$document->content_hash.'"')->header('Content-Disposition', 'attachment');
    }

    public function overwrite(Request $request, string $documentId)
    {
        $document = $this->documents->overwrite($this->document($request, $documentId), $request->getContent(), $request->header('If-Match'), $request->header('Content-Type'));
        return response()->json(['document' => (new DocumentResource($document))->resolve($request)])->header('ETag', '"'.$document->content_hash.'"');
    }

    public function append(EventBatchRequest $request)
    {
        $data = $request->validated();
        // Preserve {} versus [] in opaque payloads; associative JSON decoding loses that distinction.
        $data['events'] = json_decode($request->getContent())->events;
        $result = $this->runtime->append($this->session($request), $data);
        return response()->json($result['body'], $result['created'] ? 201 : 200);
    }

    public function checkpoint(Request $request)
    {
        $session = $this->session($request)->workSession;
        $checkpoint = $session->eventBatches()->max('seq_end') ?? -1;
        return response()->json(['checkpoint' => (int) $checkpoint, 'nextSeq' => $checkpoint + 1, 'closed' => $session->end_at !== null]);
    }

    public function replaySessions(Request $request)
    {
        $request->validate(['cursor' => ['sometimes', 'uuid'], 'limit' => ['sometimes', 'integer', 'between:1,100']]);
        $runtime = $this->session($request);
        $query = AssignmentWorkSession::query()->where('assignment_workspace_id', $runtime->workspace_id)->where('tool_id', $runtime->tool_id);
        if ($request->filled('cursor')) {
            $after = (clone $query)->find($request->input('cursor'));
            if (! $after) throw new ToolApiException('NOT_FOUND', 'Replay cursor not found.', 404);
            $query->where(fn ($q) => $q->where('start_at', '<', $after->start_at)->orWhere(fn ($q) => $q->where('start_at', $after->start_at)->where('id', '<', $after->id)));
        }
        $limit = (int) $request->input('limit', 50);
        $rows = $query->with('toolVersion')->withCount('eventBatches')->orderByDesc('start_at')->orderByDesc('id')->limit($limit + 1)->get();
        $page = $rows->take($limit);
        return response()->json(['sessions' => $page->map(function ($session) use ($runtime): array {
            $last = $session->eventBatches()->orderByDesc('seq_end')->first();
            return ['id' => $session->id, 'startedAt' => $session->start_at->toISOString(), 'endedAt' => $session->end_at?->toISOString(),
                'toolVersion' => ['id' => $session->tool_version_id, 'version' => $session->toolVersion->version], 'eventBatchCount' => $session->event_batches_count,
                'checkpoint' => $last?->seq_end ?? -1, 'lastReceivedAt' => $last?->received_at?->toISOString(), 'currentRuntimeCompatible' => $session->tool_version_id === $runtime->tool_version_id];
        })->values(), 'nextCursor' => $rows->count() > $limit ? $page->last()->id : null]);
    }

    public function replayEvents(Request $request, string $sessionId)
    {
        $request->validate(['afterSeq' => ['sometimes', 'integer', 'min:-1'], 'limit' => ['sometimes', 'integer', 'between:1,200']]);
        $runtime = $this->session($request);
        $session = AssignmentWorkSession::query()->whereKey($sessionId)->where('assignment_workspace_id', $runtime->workspace_id)->where('tool_id', $runtime->tool_id)->first();
        if (! $session) throw new ToolApiException('NOT_FOUND', 'Replay session not found.', 404);
        $limit = (int) $request->input('limit', 100);
        $rows = DB::table('event_batches')->where('assignment_work_session_id', $session->id)->where('seq_start', '>', (int) $request->input('afterSeq', -1))
            ->orderBy('seq_start')->limit($limit + 1)->get();
        $page = $rows->take($limit);
        return response()->json(['batches' => $page->map(fn ($batch) => ['id' => $batch->id, 'clientBatchId' => $batch->client_batch_id,
            'seqStart' => $batch->seq_start, 'seqEnd' => $batch->seq_end, 'clientAt' => \Carbon\CarbonImmutable::parse($batch->client_at)->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'receivedAt' => \Carbon\CarbonImmutable::parse($batch->received_at)->toISOString(), 'events' => json_decode($batch->events),
            'payloadHash' => $batch->payload_hash, 'previousChainHash' => $batch->previous_chain_hash, 'chainHash' => $batch->chain_hash])->values(),
            'nextAfterSeq' => $rows->count() > $limit ? $page->last()->seq_end : null]);
    }

    private function document(Request $request, string $id): Document
    {
        $document = $this->session($request)->workspace->documents()->find($id);
        if (! $document) throw new ToolApiException('NOT_FOUND', 'Document not found in this workspace.', 404);
        return $document;
    }

    private function session(Request $request): ToolRuntimeSession { return $request->attributes->get('tool_runtime'); }

    private function runtimeCookie(ToolRuntimeSession $session, string $token): Cookie
    {
        $session->loadMissing('tool');
        return new Cookie(ToolRuntimeService::COOKIE, $token, $session->expires_at,
            '/tools/'.rawurlencode($session->tool->code).'/'.$session->tool_version_id, null,
            parse_url($this->runtime->origins(), PHP_URL_SCHEME) === 'https', true, false, Cookie::SAMESITE_STRICT);
    }
}
