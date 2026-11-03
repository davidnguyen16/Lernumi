<?php

namespace App\Http\Controllers;

use App\Http\Requests\Learning\LearningIndexRequest;
use App\Models\Assessment;
use App\Models\Offering;
use App\Services\LearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningController
{
    public function __construct(private readonly LearningService $learning) {}

    public function index(LearningIndexRequest $request): JsonResponse
    {
        $all = $this->learning->accessibleOfferings($request->user(), $request);
        $perPage = (int) $request->input('perPage', 15);
        $page = (int) $request->input('page', 1);
        return response()->json(['offerings' => $all->forPage($page, $perPage)->map(fn (Offering $offering) => $this->learning->offeringData($request->user(), $offering, $request))->values(),
            'meta' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $all->count(), 'last_page' => max(1, (int) ceil($all->count() / $perPage))]]);
    }

    public function show(Request $request, Offering $offering): JsonResponse
    {
        $this->learning->authorize($request->user(), 'offering.view', $offering, $request);
        return response()->json(['offering' => $this->learning->offeringData($request->user(), $offering, $request, true)]);
    }

    public function assessment(Request $request, Assessment $assessment): JsonResponse
    {
        $this->learning->publishedAssessment($request->user(), $assessment, $request);
        $assessment->loadMissing('tools');
        $data = $this->learning->assessmentData($request->user(), $assessment, $request);
        $data['tools'] = $assessment->tools->map(fn ($tool) => ['id' => $tool->id, 'code' => $tool->code, 'name' => $tool->name, 'description' => $tool->description]);
        return response()->json(['assessment' => $data]);
    }
}
