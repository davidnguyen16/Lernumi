<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\QueriesAcademic;
use App\Http\Requests\Academic\IndexAcademicRequest;
use App\Http\Requests\Assessments\AssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\Institution;
use App\Models\Offering;
use App\Services\AccessService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentController
{
    use QueriesAcademic;

    public function __construct(private readonly AccessService $access) {}

    public function index(IndexAcademicRequest $request, Institution $institution, Offering $offering)
    {
        $this->access->authorize($request->user(), 'assessment.view', $offering);
        $query = $offering->assessments()->with('allowedTools')->withCount('attempts')->getQuery();
        $this->searchAcademic($query, $request, ['name']);
        return AssessmentResource::collection($this->paginateAcademic($query, $request, ['name' => 'name', 'createdAt' => 'created_at', 'publishAt' => 'publish_at'], 'name'));
    }

    public function store(AssessmentRequest $request, Institution $institution, Offering $offering)
    {
        $assessment = $this->write($request, $offering);
        return (new AssessmentResource($assessment))->response()->setStatusCode(201)->header('Location', route('assessments.show', [$institution, $offering, $assessment]));
    }

    public function show(Request $request, Institution $institution, Offering $offering, Assessment $assessment): AssessmentResource
    {
        $this->access->authorize($request->user(), 'assessment.view', $offering);
        return new AssessmentResource($assessment->load('allowedTools')->loadCount('attempts'));
    }

    public function update(AssessmentRequest $request, Institution $institution, Offering $offering, Assessment $assessment): AssessmentResource
    {
        return new AssessmentResource($this->write($request, $offering, $assessment));
    }

    private function write(AssessmentRequest $request, Offering $offering, ?Assessment $assessment = null): Assessment
    {
        return DB::transaction(function () use ($request, $offering, $assessment): Assessment {
            // Every weight mutation serializes on the offering, including deletes.
            $offering = Offering::query()->lockForUpdate()->findOrFail($offering->id);
            if ($assessment) $assessment = $offering->assessments()->lockForUpdate()->findOrFail($assessment->id);
            $attributes = $request->attributesForModel();
            $weight = $attributes['weight'] ?? $assessment?->weight ?? 0;
            $otherWeights = $offering->assessments()->when($assessment, fn ($query) => $query->whereKeyNot($assessment->id))->sum('weight');
            if ((int) round((float) $otherWeights * 100) + (int) round((float) $weight * 100) > 10000)
                throw ValidationException::withMessages(['weight' => ['The total assessment weight for this offering cannot exceed 100%.']]);
            if ($assessment) {
                $locked = $assessment->attempts()->exists() || $assessment->groups()->exists();
                if ($locked && isset($attributes['is_group']) && $attributes['is_group'] !== $assessment->is_group)
                    throw ValidationException::withMessages(['isGroup' => ['The group mode cannot change after a group or attempt exists.']]);
                if ($locked && ($request->has('allowedToolIds') || $request->isMethod('PUT'))) {
                    $existing = $assessment->allowedTools()->pluck('tools.id')->sort()->values()->all();
                    $updated = collect($request->validated('allowedToolIds', []))->sort()->values()->all();
                    if ($existing !== $updated) throw ValidationException::withMessages(['allowedToolIds' => ['Allowed tools cannot change after a group or attempt exists.']]);
                }
                $assessment->update($attributes);
            } else $assessment = $offering->assessments()->create($attributes);
            if ($request->has('allowedToolIds') || $request->isMethod('PUT')) $assessment->allowedTools()->sync($request->validated('allowedToolIds', []));
            return $assessment->load('allowedTools')->loadCount('attempts');
        });
    }

    public function destroy(Request $request, Institution $institution, Offering $offering, Assessment $assessment)
    {
        $this->access->authorize($request->user(), 'assessment.delete', $offering);
        DB::transaction(function () use ($offering, $assessment): void {
            Offering::query()->lockForUpdate()->findOrFail($offering->id);
            abort_if($assessment->attempts()->exists() || $assessment->groups()->exists(), 409, 'Assessments with attempts or groups must be retained.');
            try { $assessment->delete(); }
            catch (QueryException $exception) { if ($exception->getCode() !== '23503') throw $exception; abort(409, 'This assessment still has linked records.'); }
        });
        return response()->noContent();
    }
}
