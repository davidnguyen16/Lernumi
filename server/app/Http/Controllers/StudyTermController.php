<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\QueriesAcademic;
use App\Http\Requests\Academic\IndexAcademicRequest;
use App\Http\Requests\Terms\StudyTermRequest;
use App\Http\Resources\StudyTermResource;
use App\Models\Institution;
use App\Models\StudyTerm;
use App\Services\AccessService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudyTermController
{
    use QueriesAcademic;

    public function __construct(private readonly AccessService $access) {}

    public function index(IndexAcademicRequest $request, Institution $institution)
    {
        $this->access->authorize($request->user(), 'term.view', $institution);
        $query = $institution->terms()->with('periods')->withCount('offerings')->getQuery();
        foreach (['year', 'session'] as $field) if ($request->has($field)) $query->where($field, $request->validated($field));
        return StudyTermResource::collection($this->paginateAcademic($query, $request, ['year' => 'year', 'createdAt' => 'created_at'], '-year', ['session']));
    }

    public function store(StudyTermRequest $request, Institution $institution)
    {
        $term = $this->write($request, $institution);
        return (new StudyTermResource($term))->response()->setStatusCode(201)->header('Location', route('terms.show', [$institution, $term]));
    }

    public function show(Request $request, Institution $institution, StudyTerm $term): StudyTermResource
    {
        $this->access->authorize($request->user(), 'term.view', $institution);
        return new StudyTermResource($term->load('periods')->loadCount('offerings'));
    }

    public function update(StudyTermRequest $request, Institution $institution, StudyTerm $term): StudyTermResource
    {
        return new StudyTermResource($this->write($request, $institution, $term));
    }

    private function write(StudyTermRequest $request, Institution $institution, ?StudyTerm $term = null): StudyTerm
    {
        try {
            return DB::transaction(function () use ($request, $institution, $term): StudyTerm {
                if ($term) $term = StudyTerm::query()->lockForUpdate()->findOrFail($term->id);
                $attributes = $request->safe()->only(['year', 'session']);
                if ($term) $term->update($attributes);
                else $term = $institution->terms()->create($attributes);
                if ($request->has('periods') || $request->isMethod('PUT')) {
                    $term->periods()->delete();
                    foreach ($request->validated('periods', []) as $period) $term->periods()->create([
                        'name' => $period['name'], 'type' => $period['type'], 'start_at' => $period['startAt'], 'end_at' => $period['endAt'],
                    ]);
                }
                return $term->load('periods');
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'study_terms_institution_id_year_session_unique')) throw $exception;
            throw ValidationException::withMessages(['session' => ['This year and session already exist in this institution.']]);
        }
    }

    public function destroy(Request $request, Institution $institution, StudyTerm $term)
    {
        $this->access->authorize($request->user(), 'term.delete', $institution);
        abort_if($term->offerings()->exists(), 409, 'This term still has offerings.');
        try { $term->delete(); }
        catch (QueryException $exception) { if ($exception->getCode() !== '23503') throw $exception; abort(409, 'This term still has linked records.'); }
        return response()->noContent();
    }
}
