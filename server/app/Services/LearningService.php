<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Offering;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Shared scoped authorization and student-facing projections. */
class LearningService
{
    public function __construct(private readonly AccessService $access) {}

    public function can(User $user, string $permission, Offering $offering, ?Request $request = null): bool
    {
        if ($request?->filled('roleAssignmentId')) {
            $context = $request->input('roleAssignmentId');
            if (! is_string($context) || ! Str::isUuid($context)) return false;
            return $this->access->canInContext($user, $permission, $offering->subject->institution_id,
                $offering->id, $context);
        }
        return $this->access->can($user, $permission, $offering);
    }

    public function authorize(User $user, string $permission, Offering $offering, ?Request $request = null): void
    {
        abort_unless($this->can($user, $permission, $offering, $request), 403, 'This role cannot perform that action in this offering.');
    }

    public function publishedAssessment(User $user, Assessment $assessment, ?Request $request = null): Assessment
    {
        $assessment->loadMissing('offering.subject.institution', 'offering.term.periods');
        abort_unless($assessment->publish_at && $assessment->publish_at->lessThanOrEqualTo(now()), 404, 'Assessment not found.');
        abort_unless($this->can($user, 'offering.view', $assessment->offering, $request)
            && $this->can($user, 'assessment.view_published', $assessment->offering, $request), 404, 'Assessment not found.');
        return $assessment;
    }

    public function accessibleOfferings(User $user, Request $request): Collection
    {
        return Offering::query()->with(['subject.institution', 'term.periods', 'assessments'])
            ->when($request->input('institutionId'), fn (Builder $q, $id) => $q->whereHas('subject', fn (Builder $subjects) => $subjects->where('institution_id', $id)))
            ->when($request->input('termId'), fn (Builder $q, $id) => $q->where('term_id', $id))
            ->when($request->input('subjectId'), fn (Builder $q, $id) => $q->where('subject_id', $id))
            ->when($request->input('search'), function (Builder $q, string $search): void {
                $q->whereHas('subject', fn (Builder $subjects) => $subjects->whereRaw('(strpos(lower(subject_code), lower(?)) > 0 OR strpos(lower(subject_name), lower(?)) > 0)', [$search, $search]));
            })
            ->orderBy('id')->get()
            ->filter(fn (Offering $offering) => $this->can($user, 'offering.view', $offering, $request))->values();
    }

    public function offeringData(User $user, Offering $offering, Request $request, bool $withAssessments = false): array
    {
        $offering->loadMissing('subject.institution', 'term.periods', 'assessments');
        $canView = $this->can($user, 'assessment.view_published', $offering, $request);
        $published = $offering->assessments->filter(fn (Assessment $assessment) => $assessment->publish_at && $assessment->publish_at->lessThanOrEqualTo(now()));
        $data = [
            'id' => $offering->id,
            'institution' => ['id' => $offering->subject->institution_id, 'name' => $offering->subject->institution->name],
            'subject' => ['id' => $offering->subject_id, 'code' => $offering->subject->subject_code, 'name' => $offering->subject->subject_name, 'description' => $offering->subject->subject_description],
            'term' => ['id' => $offering->term_id, 'year' => $offering->term->year, 'session' => $offering->term->session,
                'periods' => $offering->term->periods->map(fn ($period) => ['id' => $period->id, 'name' => $period->name, 'type' => $period->type, 'startAt' => $period->start_at->toISOString(), 'endAt' => $period->end_at->toISOString()])->values()],
            'publishedAssessmentCount' => $canView ? $published->count() : null,
        ];
        if ($withAssessments) $data['assessments'] = $canView ? $published->map(fn (Assessment $assessment) => $this->assessmentData($user, $assessment, $request, false))->values() : null;
        return $data;
    }

    public function assessmentData(User $user, Assessment $assessment, Request $request, bool $withOffering = true): array
    {
        $canOwn = $this->can($user, 'attempt.view_own', $assessment->offering, $request);
        $data = [
            'id' => $assessment->id, 'name' => $assessment->name, 'description' => $assessment->description,
            'weight' => (float) $assessment->weight, 'hurdle' => $assessment->hurdle, 'isGroup' => $assessment->is_group,
            'publishAt' => $assessment->publish_at?->toISOString(), 'dueAt' => $assessment->due_at?->toISOString(), 'expectedDue' => $assessment->expected_due,
            'ownAttemptCount' => $canOwn ? AssessmentAttempt::query()->where('assessment_id', $assessment->id)->ownedBy($user)->count() : null,
        ];
        if ($withOffering) $data['offering'] = $this->offeringData($user, $assessment->offering, $request);
        return $data;
    }
}
