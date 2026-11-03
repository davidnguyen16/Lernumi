<?php

namespace App\Http\Controllers;

use App\Http\Requests\Learning\LearningIndexRequest;
use App\Http\Requests\Marking\FinaliseResultRequest;
use App\Http\Requests\Marking\UpdateMarkRequest;
use App\Http\Resources\Learning\AttemptResource;
use App\Http\Resources\Marking\MarkResource;
use App\Http\Resources\Marking\ResultResource;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptMark;
use App\Models\AssessmentAttemptResult;
use App\Models\AssignmentWorkSession;
use App\Models\Institution;
use App\Models\Offering;
use App\Services\LearningService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkingController
{
    public function __construct(private readonly LearningService $learning) {}

    public function index(LearningIndexRequest $request, Institution $institution): JsonResponse
    {
        $offerings = Offering::query()->with(['subject', 'term.periods', 'assessments'])
            ->whereHas('subject', fn (Builder $subjects) => $subjects->where('institution_id', $institution->id))
            ->when($request->input('termId'), fn (Builder $q, $id) => $q->where('term_id', $id))
            ->when($request->input('subjectId'), fn (Builder $q, $id) => $q->where('subject_id', $id))
            ->get()->filter(fn (Offering $offering) => $this->staff($request, $offering)
                || $this->learning->can($request->user(), 'assessment.view', $offering, $request));
        abort_if($offerings->isEmpty() && ! $request->user()->is_admin, 403, 'This role cannot browse marking in this institution.');
        $terms = [];
        foreach ($offerings as $offering) {
            $term = $offering->term;
            $terms[$term->id] ??= ['id' => $term->id, 'label' => $term->year.' · Session '.$term->session,
                'year' => $term->year, 'session' => $term->session,
                'current' => $term->periods->contains(fn ($period) => $period->start_at->lessThanOrEqualTo(now()) && $period->end_at->greaterThanOrEqualTo(now())),
                'periodLabel' => null, 'subjects' => []];
            $students = [];
            if ($this->staff($request, $offering)) {
                $attempts = AssessmentAttempt::query()->whereHas('assessment', fn (Builder $assessments) => $assessments->where('offering_id', $offering->id))
                    ->when($request->input('assessmentId'), fn (Builder $q, $id) => $q->where('assessment_id', $id))
                    ->when($request->input('studentId'), fn (Builder $q, $id) => $q->where(fn (Builder $owners) => $owners->where('user_id', $id)->orWhereHas('group.members', fn (Builder $members) => $members->where('user_id', $id))))
                    ->with(['user', 'assessment', 'marks', 'result.mark', 'group.members.user'])->orderByDesc('start_at')->orderByDesc('id')->get();
                foreach ($attempts as $attempt) {
                    $owners = $attempt->group ? $attempt->group->members->map(fn ($member) => $member->user) : collect([$attempt->user]);
                    foreach ($owners as $owner) {
                        $students[$owner->id] ??= ['id' => $owner->id, 'displayName' => $owner->name, 'email' => $owner->email, 'attempts' => []];
                        $students[$owner->id]['attempts'][] = ['id' => $attempt->id, 'assessmentId' => $attempt->assessment_id,
                            'assessmentName' => $attempt->assessment->name, 'startedAt' => $attempt->start_at->toISOString(),
                            'submitted' => $attempt->submit_at !== null, 'groupId' => $attempt->group_id,
                            'markCount' => $attempt->marks->where('status', '!=', 'DRAFT')->count(),
                            'hasDraft' => $attempt->marks->contains(fn ($mark) => $mark->marked_by_id === $request->user()->id && $mark->status === 'DRAFT'),
                            'officialScore' => $attempt->result ? $attempt->result->mark->score.' / '.$attempt->result->mark->out_of : null];
                    }
                }
            }
            $terms[$term->id]['subjects'][] = ['id' => $offering->subject_id, 'offeringId' => $offering->id,
                'code' => $offering->subject->subject_code, 'name' => $offering->subject->subject_name,
                'assessmentCount' => $offering->assessments->count(), 'students' => array_values($students)];
        }
        return response()->json(['terms' => array_values($terms)]);
    }

    public function show(Request $request, Institution $institution, AssessmentAttempt $attempt): JsonResponse
    {
        $this->scope($institution, $attempt);
        abort_unless($this->staff($request, $attempt->assessment->offering), 403, 'This role cannot inspect this attempt.');
        $attempt->load(['workspace.workspace', 'group.members.user', 'assessment.offering.subject', 'assessment.offering.term.periods', 'user', 'marks.marker', 'result.mark']);
        $canReplay = $this->replay($request, $attempt->assessment->offering);
        $sessions = $canReplay && $attempt->workspace ? AssignmentWorkSession::query()->where('assignment_workspace_id', $attempt->workspace->workspace_id)
            ->with(['tool', 'toolVersion'])->withCount('eventBatches')->orderBy('start_at')->orderBy('id')->get() : collect();
        $flags = [];
        if ($canReplay && $attempt->submit_at && $attempt->assessment->due_at && $attempt->submit_at->greaterThan($attempt->assessment->due_at)) {
            $flags[] = ['key' => 'submitted-after-due', 'severity' => 'NOTICE', 'label' => 'Submitted after the due time',
                'detail' => 'The recorded submission time is later than the assessment due time.', 'at' => $attempt->submit_at->toISOString()];
        }
        if ($canReplay && $attempt->submit_at && $sessions->isEmpty()) {
            $flags[] = ['key' => 'no-recorded-session', 'severity' => 'NOTICE', 'label' => 'No recorded tool session',
                'detail' => 'This attempt has no recorded assignment tool session.', 'at' => $attempt->submit_at->toISOString()];
        }
        $latest = $sessions->last();
        return response()->json([
            'attempt' => (new AttemptResource($attempt))->resolve($request),
            'assessment' => $this->learning->assessmentData($request->user(), $attempt->assessment, $request),
            'student' => ['id' => $attempt->user->id, 'displayName' => $attempt->user->name, 'email' => $attempt->user->email],
            'marks' => MarkResource::collection($attempt->marks->filter(fn ($mark) => $mark->status !== 'DRAFT' || $mark->marked_by_id === $request->user()->id))->resolve($request),
            'result' => $attempt->result ? (new ResultResource($attempt->result))->resolve($request) : null,
            'flags' => $flags,
            'sessions' => $sessions->map(fn ($session) => ['id' => $session->id, 'toolId' => $session->tool_id,
                'toolName' => $session->tool->name, 'toolVersionId' => $session->tool_version_id,
                'startedAt' => $session->start_at->toISOString(), 'endedAt' => $session->end_at?->toISOString(),
                'eventBatchCount' => $session->event_batches_count])->values(),
            'replay' => $latest ? ['workSessionId' => $latest->id, 'toolId' => $latest->tool_id, 'toolName' => $latest->tool->name, 'toolVersionId' => $latest->tool_version_id] : null,
            'canViewMarks' => true, 'canReplay' => $canReplay,
            'canMark' => $attempt->submit_at !== null && ! $attempt->isOwnedBy($request->user())
                && $this->learning->can($request->user(), 'attempt.mark', $attempt->assessment->offering, $request),
            'canFinalise' => $this->learning->can($request->user(), 'attempt.result.finalize', $attempt->assessment->offering, $request),
        ]);
    }

    public function store(Request $request, Institution $institution, AssessmentAttempt $attempt): JsonResponse
    {
        $created = false;
        $mark = DB::transaction(function () use ($request, $institution, $attempt, &$created): AssessmentAttemptMark {
            $locked = AssessmentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->markAccess($request, $institution, $locked);
            $existing = $locked->marks()->where('marked_by_id', $request->user()->id)->where('status', 'DRAFT')->first();
            if ($existing) return $existing;
            $previous = $locked->marks()->where('marked_by_id', $request->user()->id)->whereIn('status', ['SUBMITTED', 'SUPERSEDED'])->orderByDesc('submitted_at')->orderByDesc('created_at')->first();
            $created = true;
            return $locked->marks()->create(['marked_by_id' => $request->user()->id, 'supersedes_mark_id' => $previous?->id, 'out_of' => 100, 'status' => 'DRAFT']);
        });
        return response()->json(['mark' => (new MarkResource($mark->load('marker', 'attempt.result')))->resolve($request)], $created ? 201 : 200);
    }

    public function update(UpdateMarkRequest $request, Institution $institution, AssessmentAttempt $attempt, AssessmentAttemptMark $mark): JsonResponse
    {
        $updated = DB::transaction(function () use ($request, $institution, $attempt, $mark): AssessmentAttemptMark {
            $locked = AssessmentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->markAccess($request, $institution, $locked);
            $editable = $locked->marks()->whereKey($mark->id)->where('marked_by_id', $request->user()->id)->where('status', 'DRAFT')->lockForUpdate()->firstOrFail();
            $input = $request->validated();
            $score = array_key_exists('score', $input) ? $input['score'] : $editable->score;
            $outOf = $input['outOf'] ?? $editable->out_of;
            if ($score !== null && (float) $score > (float) $outOf) throw ValidationException::withMessages(['score' => 'Score cannot exceed the maximum mark.']);
            $values = [];
            foreach (['score' => 'score', 'outOf' => 'out_of', 'feedback' => 'feedback'] as $source => $target) if (array_key_exists($source, $input)) $values[$target] = $input[$source];
            $editable->update($values);
            return $editable;
        });
        return response()->json(['mark' => (new MarkResource($updated->load('marker', 'attempt.result')))->resolve($request)]);
    }

    public function submit(Request $request, Institution $institution, AssessmentAttempt $attempt, AssessmentAttemptMark $mark): JsonResponse
    {
        $submitted = DB::transaction(function () use ($request, $institution, $attempt, $mark): AssessmentAttemptMark {
            $locked = AssessmentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->markAccess($request, $institution, $locked);
            $draft = $locked->marks()->whereKey($mark->id)->where('marked_by_id', $request->user()->id)->where('status', 'DRAFT')->lockForUpdate()->firstOrFail();
            if ($draft->score === null) throw ValidationException::withMessages(['score' => 'Enter a score before submitting.']);
            if ($draft->supersedes_mark_id) $locked->marks()->whereKey($draft->supersedes_mark_id)->where('status', 'SUBMITTED')->update(['status' => 'SUPERSEDED']);
            $draft->update(['status' => 'SUBMITTED', 'submitted_at' => now()]);
            return $draft;
        });
        return response()->json(['mark' => (new MarkResource($submitted->load('marker', 'attempt.result')))->resolve($request)]);
    }

    public function finalise(FinaliseResultRequest $request, Institution $institution, AssessmentAttempt $attempt): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $institution, $attempt): AssessmentAttemptResult {
            $locked = AssessmentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->scope($institution, $locked);
            $this->learning->authorize($request->user(), 'attempt.result.finalize', $locked->assessment->offering, $request);
            abort_if($locked->isOwnedBy($request->user()), 403, 'You cannot finalise your own result.');
            abort_if($locked->submit_at === null, 409, 'Submit the attempt before finalising a result.');
            $selected = $locked->marks()->whereKey($request->validated('markId'))->where('status', 'SUBMITTED')->first();
            if (! $selected) throw ValidationException::withMessages(['markId' => 'Select a submitted mark belonging to this attempt.']);
            return AssessmentAttemptResult::query()->updateOrCreate(['attempt_id' => $locked->id],
                ['mark_id' => $selected->id, 'finalised_by_id' => $request->user()->id, 'finalised_at' => now(), 'note' => $request->validated('note')]);
        });
        return response()->json(['result' => (new ResultResource($result->load('mark.marker', 'mark.attempt.result')))->resolve($request)]);
    }

    public function clearResult(Request $request, Institution $institution, AssessmentAttempt $attempt): Response
    {
        DB::transaction(function () use ($request, $institution, $attempt): void {
            $locked = AssessmentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->scope($institution, $locked);
            $this->learning->authorize($request->user(), 'attempt.result.finalize', $locked->assessment->offering, $request);
            abort_if($locked->isOwnedBy($request->user()), 403, 'You cannot change your own result.');
            $locked->result()->delete();
        });
        return response()->noContent();
    }

    private function scope(Institution $institution, AssessmentAttempt $attempt): void
    {
        abort_unless($attempt->assessment->offering->subject->institution_id === $institution->id, 404, 'Attempt not found.');
    }

    private function markAccess(Request $request, Institution $institution, AssessmentAttempt $attempt): void
    {
        $this->scope($institution, $attempt);
        $this->learning->authorize($request->user(), 'attempt.mark', $attempt->assessment->offering, $request);
        abort_if($attempt->isOwnedBy($request->user()), 403, 'You cannot mark your own attempt.');
        abort_if($attempt->submit_at === null, 409, 'Only submitted attempts can be marked.');
    }

    private function staff(Request $request, Offering $offering): bool
    {
        foreach (['attempt.view_all', 'attempt.mark', 'attempt.result.finalize', 'tool_session.replay_all'] as $permission) {
            if ($this->learning->can($request->user(), $permission, $offering, $request)) return true;
        }
        return false;
    }

    private function replay(Request $request, Offering $offering): bool
    {
        return $this->learning->can($request->user(), 'tool_session.replay_all', $offering, $request);
    }
}
