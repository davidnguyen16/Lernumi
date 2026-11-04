<?php

namespace App\Http\Controllers;

use App\Http\Requests\Learning\LearningIndexRequest;
use App\Http\Requests\Learning\StartAttemptRequest;
use App\Http\Resources\Learning\AttemptResource;
use App\Http\Resources\Marking\ResultResource;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentGroup;
use App\Models\AssignmentWorkSession;
use App\Services\LearningService;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttemptController
{
    public function __construct(private readonly LearningService $learning, private readonly WorkspaceService $workspaces) {}

    public function index(LearningIndexRequest $request): JsonResponse
    {
        $request->validate(['assessmentId' => ['required', 'uuid']]);
        $assessment = Assessment::query()->findOrFail($request->input('assessmentId'));
        $this->learning->publishedAssessment($request->user(), $assessment, $request);
        $this->learning->authorize($request->user(), 'attempt.view_own', $assessment->offering, $request);
        $attempts = AssessmentAttempt::query()->where('assessment_id', $assessment->id)->ownedBy($request->user())
            ->with(['workspace.workspace', 'group.members.user'])->orderByDesc('start_at')->orderByDesc('id')->paginate((int) $request->input('perPage', 15));
        return response()->json(['attempts' => AttemptResource::collection($attempts)->resolve($request),
            'meta' => ['current_page' => $attempts->currentPage(), 'per_page' => $attempts->perPage(), 'total' => $attempts->total(), 'last_page' => $attempts->lastPage()]]);
    }

    public function store(StartAttemptRequest $request): JsonResponse
    {
        $attempt = DB::transaction(function () use ($request): AssessmentAttempt {
            $assessment = Assessment::query()->lockForUpdate()->findOrFail($request->validated('assessmentId'));
            $this->learning->publishedAssessment($request->user(), $assessment, $request);
            $this->learning->authorize($request->user(), 'attempt.start', $assessment->offering, $request);
            $this->learning->authorize($request->user(), 'attempt.view_own', $assessment->offering, $request);
            $groupId = $request->validated('groupId');
            if ($assessment->is_group) {
                if (! $groupId) throw ValidationException::withMessages(['groupId' => 'Select a group for this assessment.']);
                $group = AssessmentGroup::query()->where('assessment_id', $assessment->id)->lockForUpdate()->findOrFail($groupId);
                abort_unless($group->members()->where('user_id', $request->user()->id)->exists(), 403, 'Only group members can start a group attempt.');
                foreach ($group->members()->with('user')->get() as $member) {
                    abort_unless($this->learning->can($member->user, 'offering.view', $assessment->offering)
                        && $this->learning->can($member->user, 'assessment.view_published', $assessment->offering)
                        && $this->learning->can($member->user, 'attempt.start', $assessment->offering)
                        && $this->learning->can($member->user, 'attempt.view_own', $assessment->offering), 409, 'Every group member needs active assessment access.');
                }
            } elseif ($groupId) {
                throw ValidationException::withMessages(['groupId' => 'Individual assessments cannot use a group.']);
            }
            $attempt = AssessmentAttempt::query()->create(['user_id' => $request->user()->id, 'assessment_id' => $assessment->id, 'group_id' => $groupId, 'start_at' => now()]);
            $this->workspaces->forAttempt($attempt);
            return $attempt;
        });
        return response()->json(['attempt' => (new AttemptResource($attempt->load('workspace.workspace', 'group.members.user')))->resolve($request)], 201);
    }

    public function show(Request $request, AssessmentAttempt $attempt): JsonResponse
    {
        $this->own($request, $attempt);
        $canReplay = $this->learning->can($request->user(), 'tool_session.replay_own', $attempt->assessment->offering, $request);
        $sessions = $canReplay && $attempt->workspace ? AssignmentWorkSession::query()
            ->where('assignment_workspace_id', $attempt->workspace->workspace_id)->with('tool')->withCount('eventBatches')
            ->orderByDesc('start_at')->get()->map(fn ($session) => [
                'id' => $session->id, 'toolId' => $session->tool_id, 'toolName' => $session->tool->name,
                'toolVersionId' => $session->tool_version_id, 'startedAt' => $session->start_at->toISOString(),
                'endedAt' => $session->end_at?->toISOString(), 'eventBatchCount' => $session->event_batches_count,
            ])->all() : [];
        return response()->json(['attempt' => (new AttemptResource($attempt->load('workspace.workspace', 'group.members.user')))->resolve($request),
            'assessment' => $this->learning->assessmentData($request->user(), $attempt->assessment, $request),
            'result' => $attempt->result ? (new ResultResource($attempt->result))->resolve($request) : null,
            'sessions' => $sessions, 'canReplay' => $canReplay]);
    }

    public function submit(Request $request, AssessmentAttempt $attempt): JsonResponse
    {
        DB::transaction(function () use ($request, $attempt): void {
            $locked = AssessmentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->own($request, $locked);
            $this->learning->authorize($request->user(), 'attempt.submit', $locked->assessment->offering, $request);
            abort_if($locked->submit_at !== null, 409, 'This attempt has already been submitted.');
            $submittedAt = now();
            $locked->update(['submit_at' => $submittedAt]);
            $workspaceId = $locked->workspace?->workspace_id;
            if ($workspaceId) AssignmentWorkSession::query()->where('assignment_workspace_id', $workspaceId)->whereNull('end_at')->update(['end_at' => $submittedAt]);
        });
        $attempt->refresh();
        return response()->json(['attempt' => ['id' => $attempt->id, 'submitAt' => $attempt->submit_at->toISOString()]]);
    }

    private function own(Request $request, AssessmentAttempt $attempt): void
    {
        abort_unless($attempt->isOwnedBy($request->user()), 404, 'Attempt not found.');
        $this->learning->publishedAssessment($request->user(), $attempt->assessment, $request);
        $this->learning->authorize($request->user(), 'attempt.view_own', $attempt->assessment->offering, $request);
    }
}
