<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptMark;
use App\Models\AssessmentGroup;
use App\Models\AssignmentWorkSession;
use App\Models\Institution;
use App\Models\Offering;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use App\Models\StudyTerm;
use App\Models\Subject;
use App\Models\Tool;
use App\Models\ToolVersion;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/offerings')->assertUnauthorized();
        $this->postJson('/api/v1/me/attempts', [])->assertUnauthorized();
    }

    public function test_offerings_and_assessments_respect_scope_and_publication(): void
    {
        [$user, $offering, $assessment] = $this->fixture();
        [, $otherOffering] = $this->fixture();
        $draft = Assessment::query()->create(['offering_id' => $offering->id, 'name' => 'Hidden draft', 'weight' => 10, 'publish_at' => now()->addDay()]);
        $this->actingAs($user)->getJson('/api/v1/me/offerings')->assertOk()->assertJsonCount(1, 'offerings')
            ->assertJsonPath('offerings.0.id', $offering->id)->assertJsonPath('offerings.0.publishedAssessmentCount', 1);
        $this->getJson('/api/v1/me/offerings/'.$offering->id)->assertOk()->assertJsonCount(1, 'offering.assessments');
        $this->getJson('/api/v1/me/assessments/'.$assessment->id)->assertOk()->assertJsonPath('assessment.name', $assessment->name);
        $this->getJson('/api/v1/me/assessments/'.$draft->id)->assertNotFound();
        $this->getJson('/api/v1/me/offerings/'.$otherOffering->id)->assertForbidden();
    }

    public function test_selected_role_context_does_not_union_other_assignments(): void
    {
        [$user, $offering] = $this->fixture();
        $empty = $this->grant($user, $offering, ['offering.view']);
        $this->actingAs($user)->getJson('/api/v1/me/offerings/'.$offering->id.'?roleAssignmentId='.$empty->id)
            ->assertOk()->assertJsonPath('offering.assessments', null);
        $this->getJson('/api/v1/me/offerings/'.$offering->id.'?roleAssignmentId='.Str::uuid())->assertForbidden();
    }

    public function test_start_attempt_creates_workspace_and_submit_closes_sessions(): void
    {
        [$user, , $assessment] = $this->fixture();
        $response = $this->actingAs($user)->postJson('/api/v1/me/attempts', ['assessmentId' => $assessment->id])->assertCreated();
        $attempt = AssessmentAttempt::query()->findOrFail($response->json('attempt.id'));
        $workspaceId = $response->json('attempt.workspaceId');
        $this->assertDatabaseHas('assignment_workspaces', ['workspace_id' => $workspaceId, 'attempt_id' => $attempt->id]);
        [$tool, $version] = $this->tool($user);
        $session = AssignmentWorkSession::query()->create(['assignment_workspace_id' => $workspaceId, 'user_id' => $user->id,
            'tool_id' => $tool->id, 'tool_version_id' => $version->id, 'start_at' => now()]);
        $this->getJson('/api/v1/me/attempts?assessmentId='.$assessment->id)->assertOk()->assertJsonCount(1, 'attempts');
        $this->getJson('/api/v1/me/attempts/'.$attempt->id)->assertOk()->assertJsonPath('attempt.workspaceId', $workspaceId);
        $this->postJson('/api/v1/me/attempts/'.$attempt->id.'/submit')->assertOk();
        $this->assertNotNull($session->refresh()->end_at);
        $this->postJson('/api/v1/me/attempts/'.$attempt->id.'/submit')->assertConflict();
        $this->assertDatabaseCount('assessment_attempts', 1);
    }

    public function test_attempt_access_and_start_permissions_are_enforced(): void
    {
        [$user, $offering, $assessment] = $this->fixture();
        $attempt = $this->attempt($user, $assessment);
        $outsider = $this->user();
        $this->grant($outsider, $offering, ['offering.view', 'assessment.view_published', 'attempt.view_own']);
        $this->actingAs($outsider)->getJson('/api/v1/me/attempts/'.$attempt->id)->assertNotFound();
        $this->postJson('/api/v1/me/attempts', ['assessmentId' => $assessment->id])->assertForbidden();
        $assessment->update(['publish_at' => now()->addDay()]);
        $this->actingAs($user)->postJson('/api/v1/me/attempts', ['assessmentId' => $assessment->id])->assertNotFound();
    }

    public function test_individual_assessment_rejects_group_id_and_group_assessment_requires_it(): void
    {
        [$user, , $assessment] = $this->fixture();
        $this->actingAs($user)->postJson('/api/v1/me/attempts', ['assessmentId' => $assessment->id, 'groupId' => Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('groupId');
        $assessment->update(['is_group' => true]);
        $this->postJson('/api/v1/me/attempts', ['assessmentId' => $assessment->id])->assertUnprocessable()->assertJsonValidationErrors('groupId');
    }

    public function test_group_attempt_is_shared_and_membership_freezes_after_start(): void
    {
        [$user, $offering, $assessment] = $this->fixture();
        $assessment->update(['is_group' => true]);
        $partner = $this->user();
        $this->grant($partner, $offering, $this->studentPermissions());
        $base = '/api/v1/me/assessments/'.$assessment->id.'/groups';
        $groupId = $this->actingAs($user)->postJson($base, ['name' => 'Team One', 'memberIds' => [$partner->id]])
            ->assertCreated()->assertJsonCount(2, 'group.members')->json('group.id');
        $attemptId = $this->postJson('/api/v1/me/attempts', ['assessmentId' => $assessment->id, 'groupId' => $groupId])
            ->assertCreated()->json('attempt.id');
        $this->patchJson($base.'/'.$groupId, ['memberIds' => [$user->id]])->assertConflict();
        $this->deleteJson($base.'/'.$groupId)->assertConflict();
        $this->actingAs($partner)->getJson('/api/v1/me/attempts/'.$attemptId)->assertOk();
        $this->getJson('/api/v1/me/attempts?assessmentId='.$assessment->id)->assertOk()->assertJsonCount(1, 'attempts');
        $this->postJson('/api/v1/me/attempts/'.$attemptId.'/submit')->assertOk();
        $this->assertTrue(AssessmentAttempt::query()->findOrFail($attemptId)->isOwnedBy($partner));
    }

    public function test_group_members_can_be_selected_by_email_and_the_creator_is_retained(): void
    {
        [$user, $offering, $assessment] = $this->fixture();
        $assessment->update(['is_group' => true]);
        $partner = $this->user();
        $this->grant($partner, $offering, $this->studentPermissions());
        $base = '/api/v1/me/assessments/'.$assessment->id.'/groups';
        $groupId = $this->actingAs($user)->postJson($base, ['name' => 'Email group', 'memberEmails' => [' '.strtoupper($partner->email).' ']])
            ->assertCreated()->assertJsonCount(2, 'group.members')->json('group.id');
        $this->patchJson($base.'/'.$groupId, ['memberEmails' => []])->assertOk()->assertJsonCount(1, 'group.members')->assertJsonPath('group.members.0.userId', $user->id);
        $outsider = $this->user();
        $missing = $this->postJson($base, ['name' => 'Missing', 'memberEmails' => ['unknown@example.test']])->assertUnprocessable()->json('errors.memberEmails');
        $ineligible = $this->postJson($base, ['name' => 'Ineligible', 'memberEmails' => [$outsider->email]])->assertUnprocessable()->json('errors.memberEmails');
        $this->assertSame($missing, $ineligible);
        $this->postJson($base, ['name' => 'Ambiguous', 'memberIds' => [$partner->id], 'memberEmails' => [$partner->email]])->assertUnprocessable();
        $this->assertDatabaseCount('assessment_groups', 1);
    }

    public function test_attempt_owners_can_discover_replay_sessions_only_with_replay_permission(): void
    {
        [$user, $offering, $assessment] = $this->fixture();
        $attempt = $this->attempt($user, $assessment);
        [$tool, $version] = $this->tool($user);
        $session = AssignmentWorkSession::create(['assignment_workspace_id' => $attempt->workspace->workspace_id, 'tool_id' => $tool->id, 'tool_version_id' => $version->id, 'user_id' => $user->id, 'start_at' => now()]);
        $path = '/api/v1/me/attempts/'.$attempt->id;
        $this->actingAs($user)->getJson($path)->assertOk()->assertJsonPath('canReplay', true)->assertJsonCount(1, 'sessions')->assertJsonPath('sessions.0.id', $session->id)->assertJsonPath('sessions.0.toolName', $tool->name);
        $readOnly = $this->grant($user, $offering, ['offering.view', 'assessment.view_published', 'attempt.view_own']);
        $this->getJson($path.'?roleAssignmentId='.$readOnly->id)->assertOk()->assertJsonPath('canReplay', false)->assertJsonCount(0, 'sessions');
        $this->actingAs($this->user())->getJson($path)->assertNotFound();
    }

    public function test_requestable_offerings_expose_only_labels_to_members_without_an_offering_scope(): void
    {
        [$user, $offering] = $this->fixture();
        $path = '/api/v1/institutions/'.$offering->institution_id.'/requestable-offerings';
        $this->getJson($path)->assertUnauthorized();
        ScopeAssignment::whereHas('roleAssignment', fn ($query) => $query->where('user_id', $user->id))->delete();
        $this->actingAs($user)->getJson('/api/v1/institutions/'.$offering->institution_id.'/offerings')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($path)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $offering->id)
            ->assertJsonMissingPath('data.0.assessmentCount')->assertJsonMissingPath('data.0.subject.subjectDescription');
        $this->actingAs($this->user())->getJson($path)->assertForbidden();
    }

    public function test_group_members_need_assessment_access_and_can_only_join_one_group(): void
    {
        [$user, $offering, $assessment] = $this->fixture();
        $assessment->update(['is_group' => true]);
        $outsider = $this->user();
        $base = '/api/v1/me/assessments/'.$assessment->id.'/groups';
        $this->actingAs($user)->postJson($base, ['name' => 'Invalid', 'memberIds' => [$outsider->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('memberIds');
        $this->postJson($base, ['name' => 'One'])->assertCreated();
        $this->postJson($base, ['name' => 'Two'])->assertUnprocessable()->assertJsonValidationErrors('memberIds');
        $this->assertDatabaseCount('assessment_groups', 1);
    }

    public function test_group_owner_can_update_members_before_attempt_and_other_members_cannot(): void
    {
        [$user, $offering, $assessment] = $this->fixture();
        $assessment->update(['is_group' => true]);
        $partner = $this->user();
        $this->grant($partner, $offering, $this->studentPermissions());
        $base = '/api/v1/me/assessments/'.$assessment->id.'/groups';
        $id = $this->actingAs($user)->postJson($base, ['name' => 'One'])->assertCreated()->json('group.id');
        $this->patchJson($base.'/'.$id, ['memberIds' => [$user->id, $partner->id], 'name' => 'Two'])->assertOk()->assertJsonCount(2, 'group.members');
        $this->actingAs($partner)->patchJson($base.'/'.$id, ['name' => 'Three'])->assertForbidden();
        $this->actingAs($user)->deleteJson($base.'/'.$id)->assertNoContent();
        $this->assertDatabaseCount('assessment_group_members', 0);
    }

    public function test_marking_draft_is_resumable_validated_and_submitted_marks_are_immutable(): void
    {
        [$student, $offering, $assessment] = $this->fixture();
        $attempt = $this->attempt($student, $assessment, true);
        $marker = $this->marker($offering);
        $base = '/api/v1/institutions/'.$offering->institution_id.'/attempts/'.$attempt->id.'/marks';
        $id = $this->actingAs($marker)->postJson($base)->assertCreated()->assertJsonPath('mark.outOf', '100.00')->json('mark.id');
        $this->postJson($base)->assertOk()->assertJsonPath('mark.id', $id);
        $this->postJson($base.'/'.$id.'/submit')->assertUnprocessable()->assertJsonValidationErrors('score');
        $this->patchJson($base.'/'.$id, ['score' => 80, 'outOf' => 50])->assertUnprocessable()->assertJsonValidationErrors('score');
        $this->patchJson($base.'/'.$id, ['score' => 80.5, 'feedback' => 'Good work'])->assertOk()->assertJsonPath('mark.score', '80.50');
        $this->patchJson($base.'/'.$id, ['outOf' => 70])->assertUnprocessable();
        $this->postJson($base.'/'.$id.'/submit')->assertOk()->assertJsonPath('mark.status', 'SUBMITTED');
        $this->patchJson($base.'/'.$id, ['score' => 90])->assertNotFound();
        $this->assertDatabaseCount('assessment_attempt_results', 0);
    }

    public function test_mark_revisions_preserve_history_and_result_is_explicit(): void
    {
        [$student, $offering, $assessment] = $this->fixture();
        $attempt = $this->attempt($student, $assessment, true);
        $marker = $this->marker($offering);
        $base = '/api/v1/institutions/'.$offering->institution_id.'/attempts/'.$attempt->id;
        $first = $this->actingAs($marker)->postJson($base.'/marks')->assertCreated()->json('mark.id');
        $this->patchJson($base.'/marks/'.$first, ['score' => 70])->assertOk();
        $this->postJson($base.'/marks/'.$first.'/submit')->assertOk();
        $this->putJson($base.'/result', ['markId' => $first])->assertOk()->assertJsonPath('result.markId', $first);
        $second = $this->postJson($base.'/marks')->assertCreated()->assertJsonPath('mark.supersedesMarkId', $first)->json('mark.id');
        $this->patchJson($base.'/marks/'.$second, ['score' => 75])->assertOk();
        $this->putJson($base.'/result', ['markId' => $second])->assertUnprocessable();
        $this->postJson($base.'/marks/'.$second.'/submit')->assertOk();
        $this->assertDatabaseHas('assessment_attempt_marks', ['id' => $first, 'status' => 'SUPERSEDED', 'score' => 70]);
        $this->assertDatabaseHas('assessment_attempt_results', ['attempt_id' => $attempt->id, 'mark_id' => $first]);
        $this->putJson($base.'/result', ['markId' => $second])->assertOk();
        $this->actingAs($student)->getJson('/api/v1/me/attempts/'.$attempt->id)->assertOk()->assertJsonPath('result.mark.score', '75.00');
        $this->actingAs($marker)->deleteJson($base.'/result')->assertNoContent();
        $this->assertDatabaseCount('assessment_attempt_marks', 2);
    }

    public function test_only_marker_can_change_own_draft_and_self_marking_is_forbidden(): void
    {
        [$student, $offering, $assessment] = $this->fixture();
        $attempt = $this->attempt($student, $assessment, true);
        $marker = $this->marker($offering);
        $otherMarker = $this->marker($offering);
        $base = '/api/v1/institutions/'.$offering->institution_id.'/attempts/'.$attempt->id.'/marks';
        $id = $this->actingAs($marker)->postJson($base)->assertCreated()->json('mark.id');
        $this->actingAs($otherMarker)->patchJson($base.'/'.$id, ['score' => 60])->assertNotFound();
        $this->grant($student, $offering, ['attempt.mark', 'attempt.result.finalize']);
        $this->actingAs($student)->postJson($base)->assertForbidden();
        $this->putJson(str_replace('/marks', '/result', $base), ['markId' => $id])->assertForbidden();
    }

    public function test_active_attempts_cannot_be_marked_and_wrong_institution_is_hidden(): void
    {
        [$student, $offering, $assessment] = $this->fixture();
        $attempt = $this->attempt($student, $assessment);
        $marker = $this->marker($offering);
        $base = '/api/v1/institutions/'.$offering->institution_id.'/attempts/'.$attempt->id;
        $this->actingAs($marker)->postJson($base.'/marks')->assertConflict();
        $other = Institution::query()->create(['name' => 'Other']);
        $this->getJson('/api/v1/institutions/'.$other->id.'/attempts/'.$attempt->id)->assertNotFound();
        $this->actingAs($student)->getJson($base)->assertForbidden();
    }

    public function test_marking_tree_and_detail_return_scoped_data_without_other_markers_drafts(): void
    {
        [$student, $offering, $assessment] = $this->fixture();
        $attempt = $this->attempt($student, $assessment, true);
        $marker = $this->marker($offering);
        $otherMarker = $this->marker($offering);
        AssessmentAttemptMark::query()->create(['attempt_id' => $attempt->id, 'marked_by_id' => $otherMarker->id]);
        $this->actingAs($marker)->getJson('/api/v1/institutions/'.$offering->institution_id.'/marking')
            ->assertOk()->assertJsonCount(1, 'terms.0.subjects.0.students')->assertJsonPath('terms.0.subjects.0.students.0.id', $student->id);
        $this->getJson('/api/v1/institutions/'.$offering->institution_id.'/attempts/'.$attempt->id)
            ->assertOk()->assertJsonCount(0, 'marks')->assertJsonPath('canMark', true)->assertJsonPath('replay', null);
    }

    public function test_calendar_events_validate_ownership_partial_updates_and_duration(): void
    {
        [$user, $offering] = $this->fixture();
        $base = '/api/v1/me/calendar-events';
        $input = ['title' => 'Study', 'note' => 'Notes', 'startAt' => '2026-11-01T10:00:00+11:00', 'endAt' => '2026-11-01T11:00:00+11:00', 'offeringId' => $offering->id];
        $id = $this->actingAs($user)->postJson($base, $input)->assertCreated()->json('event.id');
        $this->patchJson($base.'/'.$id, ['note' => null])->assertOk()->assertJsonPath('event.note', null)->assertJsonPath('event.title', 'Study');
        $this->patchJson($base.'/'.$id, ['endAt' => '2026-11-03T11:00:00+11:00'])->assertUnprocessable()->assertJsonValidationErrors('endAt');
        $this->postJson($base, array_replace($input, ['endAt' => $input['startAt']]))->assertUnprocessable();
        $this->postJson($base, array_replace($input, ['startAt' => 'tomorrow']))->assertUnprocessable();
        $this->getJson($base)->assertOk()->assertJsonCount(1, 'events');
        $outsider = $this->user();
        $this->actingAs($outsider)->patchJson($base.'/'.$id, ['title' => 'Changed'])->assertNotFound();
        $this->deleteJson($base.'/'.$id)->assertNotFound();
        $this->getJson($base)->assertOk()->assertJsonCount(0, 'events');
        $this->actingAs($user)->deleteJson($base.'/'.$id)->assertNoContent();
    }

    public function test_calendar_cannot_link_inaccessible_offerings(): void
    {
        [$user] = $this->fixture();
        [, $other] = $this->fixture();
        $this->actingAs($user)->postJson('/api/v1/me/calendar-events', ['title' => 'Blocked', 'startAt' => '2026-11-01T10:00:00Z', 'endAt' => '2026-11-01T11:00:00Z', 'offeringId' => $other->id])->assertForbidden();
        $this->assertDatabaseCount('calendar_events', 0);
    }

    public function test_assessment_plans_are_upserts_of_valid_civil_dates_and_require_access(): void
    {
        [$user, , $assessment] = $this->fixture();
        $base = '/api/v1/me/assessment-plans/'.$assessment->id;
        $this->actingAs($user)->putJson($base, ['startDate' => '2026-11-01', 'endDate' => '2026-11-05'])->assertOk()->assertJsonPath('plan.startDate', '2026-11-01');
        $this->putJson($base, ['startDate' => '2026-11-02', 'endDate' => '2026-11-02'])->assertOk();
        $this->assertDatabaseCount('assessment_plans', 1);
        $this->putJson($base, ['startDate' => '2026-11-10', 'endDate' => '2026-11-01'])->assertUnprocessable();
        $this->putJson($base, ['startDate' => '2026-02-30', 'endDate' => '2026-11-01'])->assertUnprocessable();
        $this->getJson('/api/v1/me/calendar')->assertOk()->assertJsonCount(1, 'plans')->assertJsonCount(1, 'terms')->assertJsonCount(1, 'offerings');
        $this->actingAs($this->user())->putJson($base, ['startDate' => '2026-11-01', 'endDate' => '2026-11-02'])->assertNotFound();
        $this->actingAs($user)->deleteJson($base)->assertNoContent();
        $this->deleteJson($base)->assertNoContent();
    }

    public function test_revoking_scope_removes_learning_and_attempt_access(): void
    {
        [$user, $offering, $assessment] = $this->fixture();
        $attempt = $this->attempt($user, $assessment);
        ScopeAssignment::query()->where('offering_id', $offering->id)->update(['revoked_at' => now()]);
        $this->actingAs($user)->getJson('/api/v1/me/offerings')->assertOk()->assertJsonCount(0, 'offerings');
        $this->getJson('/api/v1/me/attempts/'.$attempt->id)->assertNotFound();
    }

    private function fixture(): array
    {
        $institution = Institution::query()->create(['name' => 'Learning University '.Str::random(6)]);
        $subject = $institution->subjects()->create(['subject_code' => 'COMP'.random_int(1000, 9999), 'subject_name' => 'Programming']);
        $term = StudyTerm::query()->create(['institution_id' => $institution->id, 'year' => 2026, 'session' => 2]);
        $offering = Offering::query()->create(['institution_id' => $institution->id, 'subject_id' => $subject->id, 'term_id' => $term->id]);
        $assessment = Assessment::query()->create(['offering_id' => $offering->id, 'name' => 'Assignment', 'weight' => 30,
            'hurdle' => false, 'is_group' => false, 'publish_at' => now()->subDay(), 'due_at' => now()->addWeek()]);
        $user = $this->user();
        $this->grant($user, $offering, $this->studentPermissions());
        return [$user, $offering, $assessment];
    }

    private function studentPermissions(): array
    {
        return ['offering.view', 'assessment.view_published', 'assessment.attempt', 'attempt.view_own', 'tool.launch', 'tool_session.replay_own'];
    }

    private function user(): User
    {
        return User::query()->create(['name' => 'Test Learner', 'email' => Str::uuid().'@example.test', 'password' => 'password123!'])->refresh();
    }

    private function grant(User $user, Offering $offering, array $permissions): RoleAssignment
    {
        $role = Role::query()->create(['institution_id' => $offering->institution_id, 'role_code' => 'role-'.Str::uuid(), 'role_name' => 'Test role', 'scope_type' => 'OFFERING']);
        $role->permissions()->sync($permissions);
        $assignment = RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'granted_at' => now()]);
        ScopeAssignment::query()->create(['role_assignment_id' => $assignment->id, 'offering_id' => $offering->id, 'granted_at' => now()]);
        return $assignment;
    }

    private function marker(Offering $offering): User
    {
        $marker = $this->user();
        $this->grant($marker, $offering, ['offering.view', 'assessment.view', 'attempt.view_all', 'attempt.mark', 'attempt.result.finalize', 'tool_session.replay_all']);
        return $marker;
    }

    private function attempt(User $user, Assessment $assessment, bool $submitted = false): AssessmentAttempt
    {
        $attempt = AssessmentAttempt::query()->create(['user_id' => $user->id, 'assessment_id' => $assessment->id,
            'start_at' => now()->subHour(), 'submit_at' => $submitted ? now() : null]);
        app(WorkspaceService::class)->forAttempt($attempt);
        return $attempt;
    }

    private function tool(User $user): array
    {
        $tool = Tool::query()->create(['code' => 'editor', 'name' => 'Editor']);
        $version = ToolVersion::query()->create(['tool_id' => $tool->id, 'created_by_id' => $user->id, 'version' => '1.0.0',
            'entry_path' => 'index.html', 'manifest' => ['schemaVersion' => 1, 'code' => 'editor', 'version' => '1.0.0', 'entry' => 'index.html'],
            'archive_key' => Str::uuid().'.zip', 'storage_prefix' => (string) Str::uuid(), 'sha256' => str_repeat('a', 64),
            'compressed_size' => 1, 'unpacked_size' => 1, 'file_count' => 1, 'created_at' => now()]);
        return [$tool, $version];
    }
}
