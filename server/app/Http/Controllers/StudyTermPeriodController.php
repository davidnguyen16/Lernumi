<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\QueriesAcademic;
use App\Http\Requests\Academic\IndexAcademicRequest;
use App\Http\Requests\Terms\StudyTermPeriodRequest;
use App\Http\Resources\StudyTermPeriodResource;
use App\Models\Institution;
use App\Models\StudyTerm;
use App\Models\StudyTermPeriod;
use App\Services\AccessService;
use Illuminate\Http\Request;

class StudyTermPeriodController
{
    use QueriesAcademic;

    public function __construct(private readonly AccessService $access) {}

    public function index(IndexAcademicRequest $request, Institution $institution, StudyTerm $term)
    {
        $this->access->authorize($request->user(), 'term.view', $institution);
        $query = $term->periods()->reorder()->getQuery();
        $this->searchAcademic($query, $request, ['name']);
        if ($request->has('type')) $query->where('type', $request->validated('type'));
        return StudyTermPeriodResource::collection($query->orderBy('start_at')->orderBy('id')->paginate((int) $request->validated('per_page', 15))->withQueryString());
    }

    public function store(StudyTermPeriodRequest $request, Institution $institution, StudyTerm $term)
    {
        $period = $term->periods()->create($request->attributesForModel());
        return (new StudyTermPeriodResource($period))->response()->setStatusCode(201)->header('Location', route('periods.show', [$institution, $term, $period]));
    }

    public function show(Request $request, Institution $institution, StudyTerm $term, StudyTermPeriod $period): StudyTermPeriodResource
    {
        $this->access->authorize($request->user(), 'term.view', $institution);
        return new StudyTermPeriodResource($period);
    }

    public function update(StudyTermPeriodRequest $request, Institution $institution, StudyTerm $term, StudyTermPeriod $period): StudyTermPeriodResource
    {
        $period->update($request->attributesForModel());
        return new StudyTermPeriodResource($period);
    }

    public function destroy(Request $request, Institution $institution, StudyTerm $term, StudyTermPeriod $period)
    {
        $this->access->authorize($request->user(), 'term.update', $institution);
        $period->delete();
        return response()->noContent();
    }
}
