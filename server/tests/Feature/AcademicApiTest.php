<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Institution;
use App\Models\Offering;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use App\Models\StudyTerm;
use App\Models\Subject;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user(true);
        $this->actingAs($this->admin);
    }

    public function test_academic_endpoints_require_authentication(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/institutions')->assertUnauthorized();
        $this->postJson('/api/v1/admin/institutions', ['name' => 'University'])->assertUnauthorized();
    }

    public function test_institution_create_patch_put_and_delete(): void
    {
        $response = $this->postJson('/api/v1/admin/institutions', ['name' => '  New university  ', 'website' => 'https://example.test/university']);
        $response->assertCreated()->assertJsonPath('data.name', 'New university');
        $id = $response->json('data.id');
        $url = '/api/v1/institutions/'.$id;
        $this->assertStringEndsWith($url, $response->headers->get('Location'));
        $this->patchJson($url, ['name' => 'Updated university'])->assertOk()->assertJsonPath('data.website', 'https://example.test/university');
        $this->putJson($url, ['name' => 'Replacement'])->assertOk()->assertJsonPath('data.website', null);
        $this->deleteJson('/api/v1/admin/institutions/'.$id)->assertNoContent();
        $this->getJson($url)->assertNotFound();
    }

    public function test_institution_validation_and_administration_permissions(): void
    {
        $this->postJson('/api/v1/admin/institutions', ['name' => str_repeat('a', 201), 'website' => 'ftp://example.test'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'website']);
        $institution = $this->institution();
        $this->actingAs($this->user())->postJson('/api/v1/admin/institutions', ['name' => 'Forbidden'])->assertForbidden();
        $this->getJson('/api/v1/admin/institutions')->assertForbidden();
        $this->getJson('/api/v1/institutions/'.$institution->id)->assertForbidden();
        $this->patchJson('/api/v1/institutions/'.$institution->id, ['name' => 'Forbidden'])->assertForbidden();
        $this->deleteJson('/api/v1/admin/institutions/'.$institution->id)->assertForbidden();
    }

    public function test_institution_listing_search_pagination_and_scoped_visibility(): void
    {
        $first = $this->institution('Alpha 100% University');
        $this->institution('Beta 100 University');
        $this->getJson('/api/v1/admin/institutions?search=100%25&perPage=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $user = $this->user();
        $this->grant($user, $first, ['institution.settings.view']);
        $this->actingAs($user)->getJson('/api/v1/institutions')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);
        $this->getJson('/api/v1/institutions/'.$first->id)->assertOk();
        $this->patchJson('/api/v1/institutions/'.$first->id, ['name' => 'Read only'])->assertForbidden();
    }

    public function test_term_creation_and_period_replacement_are_validated_and_atomic(): void
    {
        $institution = $this->institution();
        $url = $this->institutionUrl($institution).'/terms';
        $response = $this->postJson($url, ['year' => 2026, 'session' => 1, 'periods' => [$this->periodData()]]);
        $response->assertCreated()->assertJsonPath('data.year', 2026)->assertJsonPath('data.periods.0.type', 'TEACHING');
        $id = $response->json('data.id');
        $this->postJson($url, ['year' => 2026, 'session' => 1])->assertUnprocessable()->assertJsonValidationErrors('session');
        $this->patchJson($url.'/'.$id, ['periods' => [$this->periodData(['endAt' => '2026-01-01T00:00:00Z'])]])
            ->assertUnprocessable()->assertJsonValidationErrors('periods.0.endAt');
        $this->getJson($url.'/'.$id)->assertOk()->assertJsonCount(1, 'data.periods');
        $this->patchJson($url.'/'.$id, ['year' => 2027])->assertOk()->assertJsonCount(1, 'data.periods');
        $this->putJson($url.'/'.$id, ['year' => 2027, 'session' => 2])->assertOk()->assertJsonCount(0, 'data.periods');
        $other = $this->institution();
        $this->postJson($this->institutionUrl($other).'/terms', ['year' => 2027, 'session' => 2])->assertCreated();
        $this->getJson($url.'?year=2027&session=2')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson($url.'/'.$id)->assertNoContent();
    }

    public function test_term_and_period_full_rest_and_partial_date_validation(): void
    {
        [$institution, , $term] = $this->academic();
        $url = $this->institutionUrl($institution).'/terms/'.$term->id.'/periods';
        $id = $this->postJson($url, $this->periodData())->assertCreated()->json('data.id');
        $this->patchJson($url.'/'.$id, ['startAt' => '2026-03-01T00:00:00Z'])->assertUnprocessable()->assertJsonValidationErrors('endAt');
        $this->patchJson($url.'/'.$id, ['name' => 'Exam week', 'type' => 'EXAM'])->assertOk()->assertJsonPath('data.name', 'Exam week');
        $this->getJson($url.'?type=EXAM&search=exam')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($url.'/'.$id)->assertOk();
        $this->putJson($url.'/'.$id, ['name' => 'Missing fields'])->assertUnprocessable()->assertJsonValidationErrors(['type', 'startAt', 'endAt']);
        $this->deleteJson($url.'/'.$id)->assertNoContent();
        $this->getJson($url.'/'.$id)->assertNotFound();
    }

    public function test_nested_resource_bindings_reject_foreign_parents(): void
    {
        [$institution, , $term, $offering] = $this->academic();
        [$other, , $otherTerm, $otherOffering] = $this->academic();
        $period = $term->periods()->create(['name' => 'Period', 'type' => 'BREAK', 'start_at' => now(), 'end_at' => now()->addDay()]);
        $assessment = $this->assessment($offering);
        $this->getJson($this->institutionUrl($other).'/terms/'.$term->id)->assertNotFound();
        $this->getJson($this->institutionUrl($institution).'/terms/'.$otherTerm->id.'/periods/'.$period->id)->assertNotFound();
        $this->getJson($this->institutionUrl($other).'/offerings/'.$offering->id)->assertNotFound();
        $this->getJson($this->institutionUrl($other).'/offerings/'.$otherOffering->id.'/assessments/'.$assessment->id)->assertNotFound();
        $this->getJson('/api/v1/institutions/not-a-uuid/terms')->assertNotFound();
    }

    public function test_offering_unique_pair_and_same_institution_are_required(): void
    {
        [$institution, $subject, $term] = $this->academic(false);
        [, $otherSubject, $otherTerm] = $this->academic(false);
        $url = $this->institutionUrl($institution).'/offerings';
        $this->postJson($url, ['subjectId' => $otherSubject->id, 'termId' => $term->id])->assertUnprocessable()->assertJsonValidationErrors('subjectId');
        $this->postJson($url, ['subjectId' => $subject->id, 'termId' => $otherTerm->id])->assertUnprocessable()->assertJsonValidationErrors('termId');
        $id = $this->postJson($url, ['subjectId' => $subject->id, 'termId' => $term->id])->assertCreated()->json('data.id');
        $this->postJson($url, ['subjectId' => $subject->id, 'termId' => $term->id])->assertUnprocessable()->assertJsonValidationErrors('subjectId');
        $this->patchJson($url.'/'.$id, ['termId' => $term->id])->assertOk()->assertJsonPath('data.term.id', $term->id);
        $this->getJson($url.'/'.$id)->assertOk()->assertJsonPath('data.subject.subjectCode', 'COMP1000');
        $this->getJson($url.'?subjectId='.$subject->id.'&termId='.$term->id)->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson($url.'/'.$id)->assertNoContent();
    }

    public function test_database_enforces_same_institution_even_for_direct_offering_inserts(): void
    {
        [$institution, $subject] = $this->academic(false);
        [, , $foreignTerm] = $this->academic(false);
        try {
            DB::transaction(fn () => Offering::create(['institution_id' => $institution->id, 'subject_id' => $subject->id, 'term_id' => $foreignTerm->id]));
            $this->fail('The foreign term must be rejected by PostgreSQL.');
        } catch (QueryException $exception) { $this->assertSame('23503', $exception->getCode()); }
        $this->assertDatabaseCount('offerings', 0);
    }

    public function test_offering_scoped_roles_only_see_assigned_offerings(): void
    {
        [$institution, , , $offering] = $this->academic();
        $other = $this->academic()[3];
        $user = $this->user();
        $this->grant($user, $institution, ['offering.view', 'assessment.view'], $offering);
        $this->actingAs($user)->getJson($this->institutionUrl($institution).'/offerings')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->institutionUrl($institution).'/offerings/'.$offering->id)->assertOk();
        $this->getJson($this->institutionUrl($other->institution).'/offerings/'.$other->id)->assertForbidden();
        $this->postJson($this->institutionUrl($institution).'/offerings', ['subjectId' => $offering->subject_id, 'termId' => $offering->term_id])->assertForbidden();
        $this->getJson($this->institutionUrl($institution).'/subjects')->assertForbidden();
        $this->getJson($this->institutionUrl($institution).'/terms')->assertForbidden();
        $this->getJson($this->assessmentUrl($offering))->assertOk();
        $this->postJson($this->assessmentUrl($offering), ['name' => 'Not allowed', 'weight' => 10])->assertForbidden();
    }

    public function test_assessment_crud_tools_and_replace_defaults(): void
    {
        [, , , $offering] = $this->academic();
        $tool = Tool::create(['code' => 'academic-tool', 'name' => 'Academic tool']);
        $url = $this->assessmentUrl($offering);
        $id = $this->postJson($url, ['name' => 'Project 100%', 'description' => 'Instructions', 'weight' => 40.25, 'hurdle' => true, 'isGroup' => true,
            'publishAt' => '2026-01-01T00:00:00Z', 'dueAt' => '2026-02-01T00:00:00Z', 'expectedDue' => 'Week 6', 'allowedToolIds' => [$tool->id]])
            ->assertCreated()->assertJsonPath('data.weight', 40.25)->assertJsonPath('data.allowedTools.0.id', $tool->id)->json('data.id');
        $this->getJson($url.'/'.$id)->assertOk()->assertJsonPath('data.attemptCount', 0);
        $this->patchJson($url.'/'.$id, ['name' => 'Revised project'])->assertOk()->assertJsonPath('data.isGroup', true)->assertJsonCount(1, 'data.allowedTools');
        $this->putJson($url.'/'.$id, ['name' => 'Replacement', 'weight' => 30])->assertOk()->assertJsonPath('data.isGroup', false)
            ->assertJsonPath('data.description', null)->assertJsonPath('data.publishAt', null)->assertJsonCount(0, 'data.allowedTools');
        $this->deleteJson($url.'/'.$id)->assertNoContent();
    }

    public function test_assessment_weight_total_cannot_exceed_one_hundred_and_updates_exclude_self(): void
    {
        [, , , $offering] = $this->academic();
        $url = $this->assessmentUrl($offering);
        $first = $this->postJson($url, ['name' => 'First', 'weight' => 70.25])->assertCreated()->json('data.id');
        $second = $this->postJson($url, ['name' => 'Second', 'weight' => 29.75])->assertCreated()->json('data.id');
        $this->postJson($url, ['name' => 'Overflow', 'weight' => 0.01])->assertUnprocessable()->assertJsonValidationErrors('weight');
        $this->patchJson($url.'/'.$first, ['name' => 'Rename'])->assertOk();
        $this->patchJson($url.'/'.$first, ['weight' => 70.26])->assertUnprocessable()->assertJsonValidationErrors('weight');
        $this->assertEquals(70.25, Assessment::findOrFail($first)->weight);
        $this->deleteJson($url.'/'.$second)->assertNoContent();
        $this->patchJson($url.'/'.$first, ['weight' => 100])->assertOk();
    }

    public function test_assessment_input_rejects_invalid_dates_weights_and_unknown_tools(): void
    {
        [, , , $offering] = $this->academic();
        $url = $this->assessmentUrl($offering);
        $this->postJson($url, ['name' => 'Invalid', 'weight' => 1.123, 'allowedToolIds' => [Str::uuid()]])
            ->assertUnprocessable()->assertJsonValidationErrors(['weight', 'allowedToolIds.0']);
        $this->postJson($url, ['name' => 'Dates', 'weight' => 10, 'publishAt' => '2026-02-01T00:00:00Z', 'dueAt' => '2026-01-01T00:00:00Z'])
            ->assertUnprocessable()->assertJsonValidationErrors('dueAt');
        $id = $this->postJson($url, ['name' => 'Valid', 'weight' => 10, 'publishAt' => '2026-01-01T00:00:00Z', 'dueAt' => '2026-02-01T00:00:00Z'])->assertCreated()->json('data.id');
        $this->patchJson($url.'/'.$id, ['publishAt' => '2026-03-01T00:00:00Z'])->assertUnprocessable()->assertJsonValidationErrors('dueAt');
        $this->patchJson($url.'/'.$id, ['publishAt' => null])->assertOk()->assertJsonPath('data.publishAt', null);
    }

    public function test_assessment_with_attempt_retains_identity_and_tools(): void
    {
        [, , , $offering] = $this->academic();
        $assessment = $this->assessment($offering);
        $tool = Tool::create(['code' => 'locked-tool', 'name' => 'Locked tool']);
        AssessmentAttempt::create(['user_id' => $this->admin->id, 'assessment_id' => $assessment->id, 'start_at' => now()]);
        $url = $this->assessmentUrl($offering).'/'.$assessment->id;
        $this->deleteJson($url)->assertConflict();
        $this->patchJson($url, ['isGroup' => true])->assertUnprocessable()->assertJsonValidationErrors('isGroup');
        $this->patchJson($url, ['allowedToolIds' => [$tool->id]])->assertUnprocessable()->assertJsonValidationErrors('allowedToolIds');
        $this->patchJson($url, ['description' => 'Clarified instructions'])->assertOk();
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'is_group' => false]);
    }

    public function test_parent_resources_with_children_cannot_be_deleted_or_reassigned(): void
    {
        [$institution, $subject, $term, $offering] = $this->academic();
        $this->deleteJson('/api/v1/admin/institutions/'.$institution->id)->assertConflict();
        $this->deleteJson($this->institutionUrl($institution).'/subjects/'.$subject->id)->assertConflict();
        $this->deleteJson($this->institutionUrl($institution).'/terms/'.$term->id)->assertConflict();
        $this->assessment($offering);
        $url = $this->institutionUrl($institution).'/offerings/'.$offering->id;
        $otherTerm = $institution->terms()->create(['year' => 2027, 'session' => 1]);
        $this->patchJson($url, ['termId' => $otherTerm->id])->assertConflict();
        $this->deleteJson($url)->assertConflict();
    }

    public function test_admin_cross_institution_lists_filter_and_reject_ordinary_users(): void
    {
        [$first, , , $offering] = $this->academic();
        $this->academic();
        $this->assessment($offering);
        foreach (['subjects', 'terms', 'offerings', 'assessments'] as $resource)
            $this->getJson('/api/v1/admin/'.$resource.'?institutionId='.$first->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $this->actingAs($this->user());
        foreach (['subjects', 'terms', 'offerings', 'assessments'] as $resource) $this->getJson('/api/v1/admin/'.$resource)->assertForbidden();
    }

    public function test_new_user_can_discover_requestable_institutions_without_settings_access(): void
    {
        $public = $this->institution('Join this university');
        $hidden = $this->institution('Private university');
        Role::create(['institution_id' => $public->id, 'role_code' => 'student', 'role_name' => 'Student', 'scope_type' => 'OFFERING', 'request_mode' => 'PUBLIC']);
        Role::create(['institution_id' => $hidden->id, 'role_code' => 'staff', 'role_name' => 'Staff', 'scope_type' => 'INSTITUTION', 'request_mode' => 'DISABLED']);
        $this->actingAs($this->user())->getJson('/api/v1/institution-directory?search=university&perPage=5')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $public->id);
        $this->getJson('/api/v1/institutions/'.$public->id)->assertForbidden();
    }

    private function user(bool $admin = false): User
    {
        return User::forceCreate(['name' => 'Academic user', 'email' => Str::uuid().'@example.test', 'password' => 'test-password', 'is_admin' => $admin])->refresh();
    }

    private function institution(string $name = 'Academic institution'): Institution { return Institution::create(['name' => $name]); }

    private function academic(bool $withOffering = true): array
    {
        $institution = $this->institution();
        $subject = $institution->subjects()->create(['subject_code' => 'COMP1000', 'subject_name' => 'Programming']);
        $term = $institution->terms()->create(['year' => 2026, 'session' => 1]);
        $offering = $withOffering ? $institution->offerings()->create(['subject_id' => $subject->id, 'term_id' => $term->id]) : null;
        return [$institution, $subject, $term, $offering];
    }

    private function assessment(Offering $offering): Assessment { return $offering->assessments()->create(['name' => 'Assessment', 'weight' => 25]); }
    private function institutionUrl(Institution $institution): string { return '/api/v1/institutions/'.$institution->id; }
    private function assessmentUrl(Offering $offering): string { return $this->institutionUrl($offering->institution).'/offerings/'.$offering->id.'/assessments'; }
    private function periodData(array $overrides = []): array { return array_replace(['name' => 'Teaching block', 'type' => 'TEACHING', 'startAt' => '2026-01-01T00:00:00Z', 'endAt' => '2026-02-01T00:00:00Z'], $overrides); }

    private function grant(User $user, Institution $institution, array $permissions, ?Offering $offering = null): void
    {
        $role = Role::create(['institution_id' => $institution->id, 'role_code' => 'test-'.Str::uuid(), 'role_name' => 'Academic test role', 'scope_type' => $offering ? 'OFFERING' : 'INSTITUTION']);
        $role->permissions()->sync($permissions);
        $assignment = RoleAssignment::create(['user_id' => $user->id, 'role_id' => $role->id, 'granted_by_id' => $this->admin->id]);
        if ($offering) ScopeAssignment::create(['role_assignment_id' => $assignment->id, 'offering_id' => $offering->id, 'granted_by_id' => $this->admin->id]);
    }
}
