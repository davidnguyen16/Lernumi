<?php

namespace App\Http\Controllers;

use App\Http\Requests\Subjects\IndexSubjectRequest;
use App\Http\Requests\Subjects\StoreSubjectRequest;
use App\Http\Requests\Subjects\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Institution;
use App\Models\Subject;
use App\Services\AccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SubjectController
{
    public function __construct(private readonly AccessService $access) {}

    public function index(IndexSubjectRequest $request, Institution $institution): AnonymousResourceCollection
    {
        $this->access->authorize($request->user(), 'subject.view', $institution);
        $search = $request->validated('search');
        $sort = $request->validated('sort', 'subjectCode');
        $sortColumn = ['subjectCode' => 'subject_code', 'name' => 'subject_name', 'createdAt' => 'created_at'][ltrim($sort, '-')];

        $subjects = $institution->subjects()
            ->withCount('offerings')
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    // strpos treats '%' and '_' literally, unlike an unescaped LIKE search.
                    $query->whereRaw('strpos(lower(subject_code), lower(?)) > 0', [$search])
                        ->orWhereRaw('strpos(lower(subject_name), lower(?)) > 0', [$search]);
                });
            })
            ->orderBy($sortColumn, str_starts_with($sort, '-') ? 'desc' : 'asc')
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

    public function show(Request $request, Institution $institution, Subject $subject): SubjectResource
    {
        $this->access->authorize($request->user(), 'subject.view', $institution);
        return new SubjectResource($subject->loadCount('offerings'));
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

    public function destroy(Request $request, Institution $institution, Subject $subject): Response
    {
        $this->access->authorize($request->user(), 'subject.delete', $institution);
        abort_if($subject->offerings()->exists(), 409, 'This subject still has offerings.');
        try { $subject->delete(); }
        catch (QueryException $exception) { if ($exception->getCode() !== '23503') throw $exception; abort(409, 'This subject still has linked records.'); }

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
