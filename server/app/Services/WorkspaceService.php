<?php

namespace App\Services;

use App\Exceptions\ToolApiException;
use App\Models\AssessmentAttempt;
use App\Models\AssignmentWorkspace;
use App\Models\PersonalWorkspace;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class WorkspaceService
{
    public function __construct(private AccessService $access) {}

    public function forAttempt(AssessmentAttempt $attempt): Workspace
    {
        return DB::transaction(function () use ($attempt): Workspace {
            AssessmentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $existing = AssignmentWorkspace::query()->where('attempt_id', $attempt->id)->first();
            if ($existing) {
                return $existing->workspace;
            }
            $workspace = Workspace::query()->create([
                'name' => mb_substr($attempt->assessment->name, 0, 120),
                'last_opened_at' => now(),
            ]);
            AssignmentWorkspace::query()->create(['workspace_id' => $workspace->id, 'attempt_id' => $attempt->id]);

            return $workspace;
        });
    }

    public function createPersonal(User $user, string $name): Workspace
    {
        return DB::transaction(function () use ($user, $name): Workspace {
            $workspace = Workspace::query()->create(['name' => $name, 'last_opened_at' => now()]);
            PersonalWorkspace::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

            return $workspace;
        });
    }

    public function authorize(User $user, Workspace $workspace, bool $write = false): void
    {
        $workspace->loadMissing(['personal', 'assignment.attempt.assessment.offering']);
        if ($workspace->personal) {
            abort_unless($workspace->personal->user_id === $user->id, 404);

            return;
        }
        $attempt = $workspace->assignment?->attempt;
        abort_unless($attempt, 404);
        $offering = $attempt->assessment->offering;
        $owner = $attempt->isOwnedBy($user);
        $canRead = $owner
            ? $this->access->can($user, 'attempt.view_own', $offering)
            : collect(['attempt.view_all', 'attempt.mark', 'attempt.result.finalize', 'tool_session.replay_all'])
                ->contains(fn (string $permission) => $this->access->can($user, $permission, $offering));
        abort_unless($canRead, 404);
        if ($write && (! $owner || $attempt->submit_at !== null)) {
            throw new ToolApiException('WORKSPACE_READ_ONLY', 'This assessment workspace is read-only.', 403);
        }
    }

    public function isReadOnly(User $user, Workspace $workspace): bool
    {
        if ($workspace->personal) {
            return false;
        }
        $attempt = $workspace->assignment?->attempt;

        return ! $attempt || ! $attempt->isOwnedBy($user) || $attempt->submit_at !== null;
    }
}
