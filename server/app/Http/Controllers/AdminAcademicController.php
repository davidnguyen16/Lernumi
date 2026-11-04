<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\QueriesAcademic;
use App\Http\Requests\Academic\IndexAcademicRequest;
use App\Http\Resources\AssessmentResource;
use App\Http\Resources\OfferingResource;
use App\Http\Resources\StudyTermResource;
use App\Http\Resources\SubjectResource;
use App\Models\Assessment;
use App\Models\Offering;
use App\Models\StudyTerm;
use App\Models\Subject;
use App\Services\AccessService;

/** Cross-institution administration lists; write operations use the scoped resource routes. */
class AdminAcademicController
{
    use QueriesAcademic;

    public function __construct(private readonly AccessService $access) {}

    public function subjects(IndexAcademicRequest $request)
    {
        abort_unless($this->access->isAdmin($request->user()), 403);
        $query = Subject::query()->withCount('offerings');
        if ($request->has('institutionId')) $query->where('institution_id', $request->validated('institutionId'));
        $this->searchAcademic($query, $request, ['subject_code', 'subject_name']);
        return SubjectResource::collection($this->paginateAcademic($query, $request, ['name' => 'subject_name', 'subjectCode' => 'subject_code', 'createdAt' => 'created_at'], 'subjectCode'));
    }

    public function terms(IndexAcademicRequest $request)
    {
        abort_unless($this->access->isAdmin($request->user()), 403);
        $query = StudyTerm::query()->with('periods')->withCount('offerings');
        foreach (['institutionId' => 'institution_id', 'year' => 'year', 'session' => 'session'] as $field => $column)
            if ($request->has($field)) $query->where($column, $request->validated($field));
        return StudyTermResource::collection($this->paginateAcademic($query, $request, ['year' => 'year', 'createdAt' => 'created_at'], '-year', ['session']));
    }

    public function offerings(IndexAcademicRequest $request)
    {
        abort_unless($this->access->isAdmin($request->user()), 403);
        $query = Offering::query()->with(['institution', 'subject', 'term.periods'])->withCount('assessments');
        foreach (['institutionId' => 'institution_id', 'subjectId' => 'subject_id', 'termId' => 'term_id'] as $field => $column)
            if ($request->has($field)) $query->where($column, $request->validated($field));
        if ($request->filled('search')) $query->whereHas('subject', fn ($subjects) => $this->searchAcademic($subjects, $request, ['subject_code', 'subject_name']));
        return OfferingResource::collection($this->paginateAcademic($query, $request, ['createdAt' => 'created_at'], '-createdAt'));
    }

    public function assessments(IndexAcademicRequest $request)
    {
        abort_unless($this->access->isAdmin($request->user()), 403);
        $query = Assessment::query()->with('allowedTools')->withCount('attempts');
        if ($request->has('institutionId')) $query->whereHas('offering', fn ($offerings) => $offerings->where('institution_id', $request->validated('institutionId')));
        if ($request->has('offeringId')) $query->where('offering_id', $request->validated('offeringId'));
        $this->searchAcademic($query, $request, ['name']);
        return AssessmentResource::collection($this->paginateAcademic($query, $request, ['name' => 'name', 'createdAt' => 'created_at', 'publishAt' => 'publish_at'], 'name'));
    }
}
