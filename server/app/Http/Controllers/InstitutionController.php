<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\QueriesAcademic;
use App\Http\Requests\Academic\IndexAcademicRequest;
use App\Http\Requests\Institutions\StoreInstitutionRequest;
use App\Http\Requests\Institutions\UpdateInstitutionRequest;
use App\Http\Resources\InstitutionResource;
use App\Models\Institution;
use App\Services\AccessService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class InstitutionController
{
    use QueriesAcademic;

    public function __construct(private readonly AccessService $access) {}

    public function index(IndexAcademicRequest $request)
    {
        $query = Institution::query();
        if ($request->routeIs('admin.*')) abort_unless($this->access->isAdmin($request->user()), 403);
        elseif (! $this->access->isAdmin($request->user())) $query->whereIn('id', $this->access->institutionIds($request->user(), 'institution.settings.view'));
        $this->searchAcademic($query, $request, ['name']);
        return InstitutionResource::collection($this->paginateAcademic($query, $request, ['name' => 'name', 'createdAt' => 'created_at'], 'name'));
    }

    public function directory(IndexAcademicRequest $request)
    {
        $query = Institution::query()->whereExists(fn ($roles) => $roles->selectRaw('1')->from('roles')
            ->whereColumn('roles.institution_id', 'institutions.id')->whereIn('roles.request_mode', ['PUBLIC', 'CONDITIONAL']));
        $this->searchAcademic($query, $request, ['institutions.name']);
        // Discovery exposes only public institution details. Role eligibility is checked separately.
        $page = $this->paginateAcademic($query, $request, ['name' => 'name', 'createdAt' => 'created_at'], 'name');
        return response()->json(['data' => $page->getCollection()->map(fn (Institution $institution) => [
            'id' => $institution->id, 'name' => $institution->name, 'website' => $institution->website,
        ])->values(), 'links' => ['first' => $page->url(1), 'last' => $page->url($page->lastPage()), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function store(StoreInstitutionRequest $request)
    {
        $institution = Institution::create($request->attributesForModel());
        return (new InstitutionResource($institution))->response()->setStatusCode(201)->header('Location', route('institutions.show', $institution));
    }

    public function show(Request $request, Institution $institution): InstitutionResource
    {
        $this->access->authorize($request->user(), 'institution.settings.view', $institution);
        return new InstitutionResource($institution);
    }

    public function update(UpdateInstitutionRequest $request, Institution $institution): InstitutionResource
    {
        $institution->update($request->attributesForModel());
        return new InstitutionResource($institution);
    }

    public function destroy(Request $request, Institution $institution)
    {
        abort_unless($this->access->isAdmin($request->user()), 403);
        // Institution removal is explicit: academic data and role/audit history must be retained.
        abort_if($institution->subjects()->exists() || $institution->terms()->exists() || $institution->offerings()->exists(), 409, 'Remove this institution\'s subjects, terms and offerings first.');
        try { $institution->delete(); }
        catch (QueryException $exception) {
            if ($exception->getCode() !== '23503') throw $exception;
            abort(409, 'This institution still has linked roles or history.');
        }
        return response()->noContent();
    }
}
