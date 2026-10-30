<?php

namespace App\Http\Controllers;

use App\Http\Requests\Subjects\IndexSubjectRequest;
use App\Http\Requests\Subjects\StoreSubjectRequest;
use App\Http\Requests\Subjects\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Institution;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SubjectController
{
    public function index(IndexSubjectRequest $request, Institution $institution): AnonymousResourceCollection
    {
        $search = $request->validated('search');

        $subjects = $institution->subjects()
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    // strpos treats '%' and '_' literally, unlike an unescaped LIKE search.
                    $query->whereRaw('strpos(lower(subject_code), lower(?)) > 0', [$search])
                        ->orWhereRaw('strpos(lower(subject_name), lower(?)) > 0', [$search]);
                });
            })
            ->orderBy('subject_code')
            ->orderBy('id')
            ->paginate((int) $request->validated('per_page', 15))
            ->withQueryString();

        return SubjectResource::collection($subjects);
    }

    public function store(StoreSubjectRequest $request, Institution $institution): JsonResponse
    {
        try {
            $subject = $institution->subjects()->create($request->subjectAttributes());
        } catch (UniqueConstraintViolationException $exception) {
            $this->rejectDuplicateCode($exception);
        }

        return (new SubjectResource($subject))->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('subjects.show', [$institution, $subject]));
    }

    public function show(Institution $institution, Subject $subject): SubjectResource
    {
        return new SubjectResource($subject);
    }

    public function update(UpdateSubjectRequest $request, Institution $institution, Subject $subject): SubjectResource
    {
        try {
            $subject->update($request->subjectAttributes());
        } catch (UniqueConstraintViolationException $exception) {
            $this->rejectDuplicateCode($exception);
        }

        return new SubjectResource($subject);
    }

    public function destroy(Institution $institution, Subject $subject): Response
    {
        $subject->delete();

        return response()->noContent();
    }

    private function rejectDuplicateCode(UniqueConstraintViolationException $exception): never
    {
        // Keep an insert/update race consistent with normal request validation.
        if (! str_contains($exception->getMessage(), 'subjects_institution_id_subject_code_unique')) {
            throw $exception;
        }

        throw ValidationException::withMessages([
            'subjectCode' => ['The subject code has already been taken for this institution.'],
        ]);
    }
}
