<?php

namespace App\Http\Controllers;

use App\Http\Requests\Groups\GroupRequest;
use App\Http\Resources\Learning\GroupResource;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\User;
use App\Services\LearningService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupController
{
    public function __construct(private readonly LearningService $learning) {}

    public function index(Request $request, Assessment $assessment): JsonResponse
    {
        $this->access($request, $assessment);
        $query = AssessmentGroup::query()->where('assessment_id', $assessment->id)->with('members.user');
        if (! $this->manager($request, $assessment)) $query->whereHas('members', fn ($members) => $members->where('user_id', $request->user()->id));
        return response()->json(['groups' => GroupResource::collection($query->orderBy('name')->orderBy('id')->get())->resolve($request)]);
    }

    public function store(GroupRequest $request, Assessment $assessment): JsonResponse
    {
        try {
            $group = DB::transaction(function () use ($request, $assessment): AssessmentGroup {
                $locked = Assessment::query()->lockForUpdate()->findOrFail($assessment->id);
                $this->access($request, $locked);
                $ids = $this->memberIds($request, $locked);
                if (! $this->manager($request, $locked) || $ids === []) $ids[] = $request->user()->id;
                $ids = array_values(array_unique($ids));
                $this->validateMembers($ids, $locked);
                $group = AssessmentGroup::query()->create(['assessment_id' => $locked->id, 'name' => $request->validated('name'), 'created_by_id' => $request->user()->id]);
                foreach ($ids as $id) $group->members()->create(['assessment_id' => $locked->id, 'user_id' => $id]);
                return $group;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') throw $exception;
            throw ValidationException::withMessages(['memberIds' => 'A member already belongs to a group for this assessment.']);
        }
        return response()->json(['group' => (new GroupResource($group->load('members.user')))->resolve($request)], 201);
    }

    public function show(Request $request, Assessment $assessment, AssessmentGroup $group): JsonResponse
    {
        $this->access($request, $assessment);
        $this->belongs($assessment, $group);
        abort_unless($this->manager($request, $assessment) || $group->members()->where('user_id', $request->user()->id)->exists(), 404, 'Group not found.');
        return response()->json(['group' => (new GroupResource($group->load('members.user')))->resolve($request)]);
    }

    public function update(GroupRequest $request, Assessment $assessment, AssessmentGroup $group): JsonResponse
    {
        try {
            DB::transaction(function () use ($request, $assessment, $group): void {
                $lockedAssessment = Assessment::query()->lockForUpdate()->findOrFail($assessment->id);
                $lockedGroup = AssessmentGroup::query()->lockForUpdate()->findOrFail($group->id);
                $this->editable($request, $lockedAssessment, $lockedGroup);
                if ($request->has('memberIds') || $request->has('memberEmails')) {
                    $ids = $this->memberIds($request, $lockedAssessment);
                    if ($request->has('memberEmails') && ! $this->manager($request, $lockedAssessment)) {
                        $ids = array_values(array_unique([...$ids, $lockedGroup->created_by_id]));
                    }
                    if (! $this->manager($request, $lockedAssessment) && ! in_array($lockedGroup->created_by_id, $ids, true)) {
                        throw ValidationException::withMessages(['memberIds' => 'The group creator must remain a member.']);
                    }
                    $this->validateMembers($ids, $lockedAssessment);
                    $lockedGroup->members()->whereNotIn('user_id', $ids)->delete();
                    foreach ($ids as $id) $lockedGroup->members()->firstOrCreate(['user_id' => $id], ['assessment_id' => $lockedAssessment->id]);
                }
                if ($request->has('name')) $lockedGroup->update(['name' => $request->validated('name')]);
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') throw $exception;
            throw ValidationException::withMessages(['memberIds' => 'A member already belongs to a group for this assessment.']);
        }
        return response()->json(['group' => (new GroupResource($group->fresh()->load('members.user')))->resolve($request)]);
    }

    public function destroy(Request $request, Assessment $assessment, AssessmentGroup $group): Response
    {
        DB::transaction(function () use ($request, $assessment, $group): void {
            $lockedAssessment = Assessment::query()->lockForUpdate()->findOrFail($assessment->id);
            $locked = AssessmentGroup::query()->lockForUpdate()->findOrFail($group->id);
            $this->editable($request, $lockedAssessment, $locked);
            $locked->delete();
        });
        return response()->noContent();
    }

    private function access(Request $request, Assessment $assessment): void
    {
        abort_unless($assessment->is_group, 409, 'This assessment uses individual attempts.');
        if (! $this->manager($request, $assessment)) {
            $this->learning->publishedAssessment($request->user(), $assessment, $request);
            $this->learning->authorize($request->user(), 'attempt.start', $assessment->offering, $request);
        }
    }

    private function manager(Request $request, Assessment $assessment): bool
    {
        return $this->learning->can($request->user(), 'assessment.update', $assessment->offering, $request);
    }

    private function belongs(Assessment $assessment, AssessmentGroup $group): void
    {
        abort_unless($group->assessment_id === $assessment->id, 404, 'Group not found.');
    }

    private function editable(Request $request, Assessment $assessment, AssessmentGroup $group): void
    {
        $this->access($request, $assessment);
        $this->belongs($assessment, $group);
        abort_unless($this->manager($request, $assessment) || $group->created_by_id === $request->user()->id, 403, 'Only the group creator or an assessment manager can change the group.');
        abort_if($group->attempts()->exists(), 409, 'Group membership is fixed after the first attempt starts.');
    }

    private function validateMembers(array $ids, Assessment $assessment): void
    {
        $users = User::query()->whereIn('id', $ids)->get();
        if ($users->count() !== count($ids)) throw ValidationException::withMessages(['memberIds' => 'Every member must be an existing user.']);
        foreach ($users as $user) {
            if (! $this->learning->can($user, 'offering.view', $assessment->offering)
                || ! $this->learning->can($user, 'assessment.view_published', $assessment->offering)
                || ! $this->learning->can($user, 'attempt.start', $assessment->offering)
                || ! $this->learning->can($user, 'attempt.view_own', $assessment->offering)) {
                throw ValidationException::withMessages(['memberIds' => 'Every member needs active access to this assessment.']);
            }
        }
    }

    private function memberIds(GroupRequest $request, Assessment $assessment): array
    {
        if (! $request->has('memberEmails')) return $request->validated('memberIds', []);
        $emails = $request->validated('memberEmails');
        $users = User::query()->whereIn('email', $emails)->where('is_active', true)->get();
        if ($users->count() !== count($emails)) {
            throw ValidationException::withMessages(['memberEmails' => 'Every member must have active access to this assessment.']);
        }
        $ids = $users->pluck('id')->all();
        try { $this->validateMembers($ids, $assessment); }
        catch (ValidationException) {
            throw ValidationException::withMessages(['memberEmails' => 'Every member must have active access to this assessment.']);
        }
        return $ids;
    }
}
