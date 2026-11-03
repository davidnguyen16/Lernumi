<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\QueriesAcademic;
use App\Http\Requests\Academic\IndexAcademicRequest;
use App\Http\Requests\Offerings\OfferingRequest;
use App\Http\Resources\OfferingResource;
use App\Models\Institution;
use App\Models\Offering;
use App\Services\AccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferingController
{
    use QueriesAcademic;

    public function __construct(private readonly AccessService $access) {}

    public function index(IndexAcademicRequest $request, Institution $institution)
    {
        $query = $institution->offerings()->with(['institution', 'subject', 'term.periods'])->withCount('assessments')->getQuery();
        if (! $this->access->isAdmin($request->user())) $query->whereIn('id', $this->access->offeringIds($request->user(), 'offering.view'));
        foreach (['subjectId' => 'subject_id', 'termId' => 'term_id'] as $field => $column)
            if ($request->has($field)) $query->where($column, $request->validated($field));
        if ($request->filled('search')) $query->whereHas('subject', fn (Builder $subjects) => $this->searchAcademic($subjects, $request, ['subject_code', 'subject_name']));
        return OfferingResource::collection($this->paginateAcademic($query, $request, ['createdAt' => 'created_at'], '-createdAt'));
    }

    public function store(OfferingRequest $request, Institution $institution)
    {
        $offering = $this->write($request, $institution);
        return (new OfferingResource($offering))->response()->setStatusCode(201)->header('Location', route('offerings.show', [$institution, $offering]));
    }

    public function show(Request $request, Institution $institution, Offering $offering): OfferingResource
    {
        $this->access->authorize($request->user(), 'offering.view', $offering);
        return new OfferingResource($offering->load(['institution', 'subject', 'term.periods'])->loadCount('assessments'));
    }

    public function update(OfferingRequest $request, Institution $institution, Offering $offering): OfferingResource
    {
        return new OfferingResource($this->write($request, $institution, $offering));
    }

    private function write(OfferingRequest $request, Institution $institution, ?Offering $offering = null): Offering
    {
        try {
            return DB::transaction(function () use ($request, $institution, $offering): Offering {
                $attributes = $request->attributesForModel();
                if ($offering) {
                    $offering = Offering::query()->lockForUpdate()->findOrFail($offering->id);
                    $changed = (isset($attributes['subject_id']) && $attributes['subject_id'] !== $offering->subject_id)
                        || (isset($attributes['term_id']) && $attributes['term_id'] !== $offering->term_id);
                    abort_if($changed && $offering->assessments()->exists(), 409, 'An offering with assessments cannot be moved to another subject or term.');
                    $offering->update($attributes);
                } else $offering = $institution->offerings()->create($attributes);
                return $offering->load(['institution', 'subject', 'term.periods'])->loadCount('assessments');
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'offerings_subject_id_term_id_unique')) throw $exception;
            throw ValidationException::withMessages(['subjectId' => ['This subject is already offered in this term.']]);
        }
    }

    public function destroy(Request $request, Institution $institution, Offering $offering)
    {
        $this->access->authorize($request->user(), 'offering.delete', $offering);
        abort_if($offering->assessments()->exists(), 409, 'This offering still has assessments.');
        try { $offering->delete(); }
        catch (QueryException $exception) { if ($exception->getCode() !== '23503') throw $exception; abort(409, 'This offering still has linked scopes or history.'); }
        return response()->noContent();
    }
}
