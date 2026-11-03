<?php

namespace App\Http\Controllers;

use App\Http\Requests\Calendar\AssessmentPlanRequest;
use App\Http\Requests\Calendar\CalendarEventRequest;
use App\Http\Requests\Learning\LearningIndexRequest;
use App\Http\Resources\Calendar\AssessmentPlanResource;
use App\Http\Resources\Calendar\CalendarEventResource;
use App\Models\Assessment;
use App\Models\AssessmentPlan;
use App\Models\CalendarEvent;
use App\Models\Offering;
use App\Services\LearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalendarController
{
    public function __construct(private readonly LearningService $learning) {}

    public function index(LearningIndexRequest $request): JsonResponse
    {
        $offerings = $this->learning->accessibleOfferings($request->user(), $request);
        $terms = $offerings->map(fn (Offering $offering) => $offering->term)->unique('id')->values();
        $assessmentIds = [];
        $canSee = false;
        $projected = $offerings->map(function (Offering $offering) use ($request, &$assessmentIds, &$canSee): array {
            $canView = $this->learning->can($request->user(), 'assessment.view_published', $offering, $request);
            $canSee = $canSee || $canView;
            $assessments = $canView ? $offering->assessments->filter(fn (Assessment $assessment) => $assessment->publish_at && $assessment->publish_at->lessThanOrEqualTo(now())) : collect();
            foreach ($assessments as $assessment) $assessmentIds[] = $assessment->id;
            return ['id' => $offering->id, 'termId' => $offering->term_id,
                'subject' => ['code' => $offering->subject->subject_code, 'name' => $offering->subject->subject_name],
                'assessments' => $assessments->map(fn (Assessment $assessment) => [
                    'id' => $assessment->id, 'offeringId' => $offering->id, 'name' => $assessment->name, 'weight' => (float) $assessment->weight,
                    'hurdle' => $assessment->hurdle, 'isGroup' => $assessment->is_group,
                    'dueAt' => $assessment->due_at?->toISOString(), 'expectedDue' => $assessment->expected_due,
                ])->values()];
        });
        return response()->json([
            'timeZone' => config('app.calendar_timezone', 'Australia/Sydney'),
            'canSeeAssessments' => $canSee,
            'terms' => $terms->map(fn ($term) => ['id' => $term->id, 'year' => $term->year, 'session' => $term->session,
                'periods' => $term->periods->map(fn ($period) => ['id' => $period->id, 'name' => $period->name, 'type' => $period->type, 'startAt' => $period->start_at->toISOString(), 'endAt' => $period->end_at->toISOString()])]),
            'offerings' => $projected,
            'events' => CalendarEventResource::collection(CalendarEvent::query()->where('user_id', $request->user()->id)->orderBy('start_at')->get())->resolve($request),
            'plans' => AssessmentPlanResource::collection(AssessmentPlan::query()->where('user_id', $request->user()->id)->whereIn('assessment_id', $assessmentIds)->get())->resolve($request),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $request->validate(['from' => ['sometimes', 'date'], 'to' => ['sometimes', 'date', ...($request->has('from') ? ['after_or_equal:from'] : [])]]);
        $events = CalendarEvent::query()->where('user_id', $request->user()->id)
            ->when($request->input('from'), fn ($q, $from) => $q->where('end_at', '>', $from))
            ->when($request->input('to'), fn ($q, $to) => $q->where('start_at', '<', $to))
            ->orderBy('start_at')->orderBy('id')->get();
        return response()->json(['events' => CalendarEventResource::collection($events)->resolve($request)]);
    }

    public function store(CalendarEventRequest $request): JsonResponse
    {
        $this->offeringAccess($request, $request->validated('offeringId'));
        $event = CalendarEvent::query()->create(['user_id' => $request->user()->id, ...$this->eventValues($request)]);
        return response()->json(['event' => (new CalendarEventResource($event))->resolve($request)], 201);
    }

    public function update(CalendarEventRequest $request, CalendarEvent $event): JsonResponse
    {
        $updated = DB::transaction(function () use ($request, $event): CalendarEvent {
            $locked = CalendarEvent::query()->where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($event->id);
            if ($request->has('offeringId')) $this->offeringAccess($request, $request->validated('offeringId'));
            $locked->fill($this->eventValues($request));
            // Validate the merged row again under its lock: concurrent partial edits may have
            // changed the opposite endpoint since FormRequest validation.
            if ($locked->end_at->lessThanOrEqualTo($locked->start_at) || $locked->start_at->diffInSeconds($locked->end_at) > 86400) {
                throw ValidationException::withMessages(['endAt' => 'Events must end after they start and last at most 24 hours.']);
            }
            $locked->save();
            return $locked;
        });
        return response()->json(['event' => (new CalendarEventResource($updated))->resolve($request)]);
    }

    public function destroy(Request $request, CalendarEvent $event): Response
    {
        abort_unless($event->user_id === $request->user()->id, 404, 'Event not found.');
        $event->delete();
        return response()->noContent();
    }

    public function plans(Request $request): JsonResponse
    {
        $all = AssessmentPlan::query()->with('assessment.offering.subject')->where('user_id', $request->user()->id)->get()
            ->filter(fn (AssessmentPlan $plan) => $plan->assessment->publish_at && $plan->assessment->publish_at->lessThanOrEqualTo(now())
                && $this->learning->can($request->user(), 'offering.view', $plan->assessment->offering, $request)
                && $this->learning->can($request->user(), 'assessment.view_published', $plan->assessment->offering, $request));
        return response()->json(['plans' => AssessmentPlanResource::collection($all)->resolve($request)]);
    }

    public function plan(AssessmentPlanRequest $request, Assessment $assessment): JsonResponse
    {
        $this->learning->publishedAssessment($request->user(), $assessment, $request);
        $plan = AssessmentPlan::query()->updateOrCreate(['user_id' => $request->user()->id, 'assessment_id' => $assessment->id],
            ['start_date' => $request->validated('startDate'), 'end_date' => $request->validated('endDate')]);
        return response()->json(['plan' => (new AssessmentPlanResource($plan))->resolve($request)]);
    }

    public function deletePlan(Request $request, Assessment $assessment): Response
    {
        AssessmentPlan::query()->where('user_id', $request->user()->id)->where('assessment_id', $assessment->id)->delete();
        return response()->noContent();
    }

    private function offeringAccess(Request $request, ?string $id): void
    {
        if ($id === null) return;
        $offering = Offering::query()->findOrFail($id);
        $this->learning->authorize($request->user(), 'offering.view', $offering, $request);
    }

    private function eventValues(CalendarEventRequest $request): array
    {
        $map = ['title' => 'title', 'note' => 'note', 'startAt' => 'start_at', 'endAt' => 'end_at', 'offeringId' => 'offering_id'];
        $values = [];
        foreach ($request->validated() as $field => $value) if (isset($map[$field])) $values[$map[$field]] = $value;
        return $values;
    }
}
