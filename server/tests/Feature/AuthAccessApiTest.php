<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Offering;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use App\Models\StudyTerm;
use App\Models\User;
use App\Services\AccessService;
use App\Services\PermissionCatalog;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAccessApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4]);
        Notification::fake();
    }

    public function test_registration_never_accepts_admin_flags_or_password_hash_and_authenticates_cookie_session(): void
    {
        $response = $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/v1/auth/register', ['firstName' => 'Test', 'lastName' => 'Student', 'email' => ' STUDENT@example.test ', 'password' => 'LongPassword123!', 'password_confirmation' => 'LongPassword123!', 'isAdmin' => true, 'is_admin' => true]);
        $response->assertCreated()->assertJsonPath('data.user.name', 'Test Student')->assertJsonPath('data.user.email', 'student@example.test')->assertJsonPath('data.user.isAdmin', false)->assertJsonPath('data.contexts', []);
        $user = User::firstOrFail();
        $this->assertTrue(Hash::check('LongPassword123!', $user->password));
        $this->getJson('/api/v1/auth/session')->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_login_errors_are_generic_and_disabled_users_cannot_authenticate(): void
    {
        $user = $this->user(['is_active' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'LongPassword123!'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'LongPassword123!'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $user->forceFill(['is_active' => true])->save();
        $this->postJson('/api/v1/auth/login', ['email' => strtoupper($user->email), 'password' => 'LongPassword123!'])->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
    }

    public function test_passwords_are_rejected_before_bcrypt_can_truncate_bytes(): void
    {
        foreach ([str_repeat('a', 72).'1', str_repeat('é', 36).'1'] as $password) {
            $this->postJson('/api/v1/auth/register', ['name' => 'Test user', 'email' => 'long@example.test', 'password' => $password, 'passwordConfirmation' => $password])->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unauthenticated_requests_are_401_and_inactive_personal_tokens_are_blocked(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $user = $this->user(['is_active' => false]);
        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_profile_does_not_accept_privilege_fields_and_email_change_requires_current_password(): void
    {
        $user = $this->user();
        $this->actingAs($user)->patchJson('/api/v1/me', ['name' => 'New name', 'isAdmin' => true])->assertOk()->assertJsonPath('data.isAdmin', false);
        $this->patchJson('/api/v1/me', ['email' => 'new@example.test'])->assertUnprocessable()->assertJsonValidationErrors('currentPassword');
        $this->patchJson('/api/v1/me', ['email' => 'new@example.test', 'currentPassword' => 'LongPassword123!'])->assertOk()->assertJsonPath('data.emailVerified', false);
    }

    public function test_password_change_revokes_all_sessions_tokens_and_changes_hash(): void
    {
        $user = $this->user();
        $user->createToken('mobile');
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($user)->putJson('/api/v1/auth/password', ['currentPassword' => 'LongPassword123!', 'password' => 'ChangedPassword456!', 'password_confirmation' => 'ChangedPassword456!'])->assertNoContent();
        $this->assertTrue(Hash::check('ChangedPassword456!', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_reset_token_can_be_used_only_once_and_revokes_tokens(): void
    {
        $user = $this->user();
        $user->createToken('mobile');
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $notification = Notification::sent($user, \App\Notifications\ResetPasswordNotification::class)->first();
        $this->assertNotNull($notification);
        $body = ['email' => $user->email, 'token' => $notification->token, 'password' => 'ResetPassword456!', 'password_confirmation' => 'ResetPassword456!'];
        $this->postJson('/api/v1/auth/reset-password', $body)->assertOk();
        $this->postJson('/api/v1/auth/reset-password', $body)->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertTrue(Hash::check('ResetPassword456!', $user->fresh()->password));
    }

    public function test_email_verification_is_signed_and_bound_to_current_account(): void
    {
        $user = $this->user(['email_verified_at' => null]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->getJson($url)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $other = $this->user(['email_verified_at' => null]);
        $this->actingAs($other)->getJson($url)->assertForbidden();
    }

    public function test_admin_cannot_disable_final_active_admin_but_can_disable_another_account(): void
    {
        $admin = $this->user(['is_admin' => true]);
        $student = $this->user();
        $student->createToken('mobile');
        $this->actingAs($admin)->patchJson('/api/v1/admin/users/'.$admin->id, ['isAdmin' => false])->assertStatus(409);
        $this->patchJson('/api/v1/admin/users/'.$student->id, ['isActive' => false])->assertOk()->assertJsonPath('data.isActive', false);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_roles_are_institution_scoped_and_cross_tenant_ids_are_rejected(): void
    {
        $admin = $this->user(['is_admin' => true]);
        $a = Institution::factory()->create();
        $b = Institution::factory()->create();
        $role = $this->role($a, ['subject.view']);
        $foreign = $this->role($b, ['subject.view']);
        $this->actingAs($admin)->patchJson('/api/v1/institutions/'.$b->id.'/roles/'.$role->id, ['roleName' => 'Stolen'])->assertNotFound();
        $this->postJson('/api/v1/institutions/'.$a->id.'/roles', ['roleCode' => 'institution:reader', 'roleName' => 'Reader', 'scopeType' => 'INSTITUTION', 'includedRoleIds' => [$foreign->id]])->assertUnprocessable()->assertJsonValidationErrors('includedRoleIds');
    }

    public function test_role_inheritance_expands_manage_bundles_and_rejects_cycles(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->role($institution, ['subject.manage']);
        $b = $this->role($institution, []);
        $student = $this->user();
        $this->assignment($student, $b);
        $admin = $this->user(['is_admin' => true]);
        $this->actingAs($admin)->patchJson('/api/v1/institutions/'.$institution->id.'/roles/'.$b->id, ['includedRoleIds' => [$a->id]])->assertOk();
        $this->assertTrue(app(AccessService::class)->can($student, 'subject.create', $institution));
        $this->patchJson('/api/v1/institutions/'.$institution->id.'/roles/'.$a->id, ['includedRoleIds' => [$b->id]])->assertUnprocessable()->assertJsonValidationErrors('includedRoleIds');
    }

    public function test_limited_role_managers_cannot_escalate_permissions_or_grant_higher_roles(): void
    {
        $institution = Institution::factory()->create();
        $limited = $this->user();
        $this->assignment($limited, $this->role($institution, ['role.manage', 'role_assignment.manage', 'subject.view']));
        $highRole = $this->role($institution, ['attempt.mark']);
        $target = $this->user();
        $this->actingAs($limited)->postJson('/api/v1/institutions/'.$institution->id.'/roles', ['roleCode' => 'institution:escalation', 'roleName' => 'Escalation', 'scopeType' => 'INSTITUTION', 'permissions' => ['attempt.mark']])->assertForbidden();
        $this->postJson('/api/v1/institutions/'.$institution->id.'/role-assignments', ['userId' => $target->id, 'roleId' => $highRole->id])->assertForbidden();
    }

    public function test_offering_scopes_restrict_access_and_revocation_takes_effect_immediately(): void
    {
        [$institution, $offering] = $this->offering();
        [, $other] = $this->offering($institution);
        $user = $this->user();
        $role = $this->role($institution, ['offering.view', 'assessment.attempt'], 'OFFERING');
        $assignment = $this->assignment($user, $role);
        $access = app(AccessService::class);
        $this->assertFalse($access->can($user, 'offering.view', $offering));
        $scope = ScopeAssignment::create(['role_assignment_id' => $assignment->id, 'offering_id' => $offering->id, 'granted_at' => now()]);
        $this->assertTrue($access->can($user, 'attempt.start', $offering));
        $this->assertFalse($access->can($user, 'offering.view', $other));
        $this->assertSame([$offering->id], $access->offeringIds($user));
        $scope->update(['revoked_at' => now()]);
        $this->assertFalse($access->can($user, 'offering.view', $offering));
    }

    public function test_explicit_context_cannot_borrow_permissions_from_another_assignment(): void
    {
        [$institution, $offering] = $this->offering();
        $user = $this->user();
        $reader = $this->assignment($user, $this->role($institution, ['offering.view']));
        $this->assignment($user, $this->role($institution, ['attempt.mark']));
        $access = app(AccessService::class);
        $this->assertTrue($access->can($user, 'attempt.mark', $offering));
        $this->assertFalse($access->canInContext($user, 'attempt.mark', $institution->id, $offering->id, $reader->id));
    }

    public function test_offering_managers_can_list_local_assignments_without_leaking_other_scopes(): void
    {
        [$institution, $offering] = $this->offering();
        [, $other] = $this->offering($institution);
        $manager = $this->user();
        $managerAssignment = $this->assignment($manager, $this->role($institution, ['role_assignment.view'], 'OFFERING'));
        ScopeAssignment::create(['role_assignment_id' => $managerAssignment->id, 'offering_id' => $offering->id]);
        $student = $this->user();
        $assignment = $this->assignment($student, $this->role($institution, ['offering.view'], 'OFFERING'));
        foreach ([$offering, $other] as $scope) ScopeAssignment::create(['role_assignment_id' => $assignment->id, 'offering_id' => $scope->id]);
        $path = '/api/v1/institutions/'.$institution->id.'/role-assignments';
        $this->actingAs($manager)->getJson($path)->assertForbidden();
        $this->getJson($path.'?offeringId='.$other->id)->assertForbidden();
        $response = $this->getJson($path.'?offeringId='.$offering->id.'&userId='.$student->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(1, 'data.0.scopeAssignments')->assertJsonPath('data.0.scopeAssignments.0.offeringId', $offering->id);
        $this->assertStringNotContainsString($other->id, $response->getContent());
    }

    public function test_role_and_scope_requests_create_auditable_assignments_and_cannot_be_reviewed_twice(): void
    {
        [$institution, $offering] = $this->offering();
        $student = $this->user();
        $admin = $this->user(['is_admin' => true]);
        $role = $this->role($institution, ['offering.view'], 'OFFERING', ['request_mode' => 'PUBLIC']);
        $request = $this->actingAs($student)->postJson('/api/v1/institutions/'.$institution->id.'/role-requests', ['roleId' => $role->id])->assertCreated()->assertJsonPath('data.user.email', $student->email)->assertJsonPath('data.role.id', $role->id)->assertJsonMissingPath('data.user.isAdmin')->json('data.id');
        $this->postJson('/api/v1/institutions/'.$institution->id.'/role-requests', ['roleId' => $role->id])->assertStatus(409);
        $assignmentId = $this->actingAs($admin)->patchJson('/api/v1/institutions/'.$institution->id.'/role-requests/'.$request, ['decision' => 'APPROVED'])->assertOk()->json('data.approvedAssignmentId');
        $this->patchJson('/api/v1/institutions/'.$institution->id.'/role-requests/'.$request, ['decision' => 'REJECTED'])->assertStatus(409);
        $scopeRequestId = $this->actingAs($student)->postJson('/api/v1/institutions/'.$institution->id.'/scope-requests', ['roleAssignmentId' => $assignmentId, 'offeringId' => $offering->id])->assertCreated()->json('data.id');
        $this->actingAs($admin)->patchJson('/api/v1/institutions/'.$institution->id.'/scope-requests/'.$scopeRequestId, ['decision' => 'APPROVED'])->assertOk()->assertJsonPath('data.user.id', $student->id)->assertJsonPath('data.offering.id', $offering->id);
        $this->assertTrue(app(AccessService::class)->can($student, 'offering.view', $offering));
        $this->assertDatabaseHas('access_audit_logs', ['action' => 'scope_request.approved', 'subject_id' => $scopeRequestId]);
    }

    public function test_disabled_and_conditional_roles_cannot_be_requested_without_prerequisites(): void
    {
        $institution = Institution::factory()->create();
        $user = $this->user();
        $prerequisite = $this->role($institution, ['institution.view']);
        $disabled = $this->role($institution, []);
        $conditional = $this->role($institution, [], 'INSTITUTION', ['request_mode' => 'CONDITIONAL']);
        $conditional->requiredRoles()->sync([$prerequisite->id]);
        $this->actingAs($user)->postJson('/api/v1/institutions/'.$institution->id.'/role-requests', ['roleId' => $disabled->id])->assertForbidden();
        $this->postJson('/api/v1/institutions/'.$institution->id.'/role-requests', ['roleId' => $conditional->id])->assertForbidden();
        $this->assignment($user, $prerequisite);
        $this->postJson('/api/v1/institutions/'.$institution->id.'/role-requests', ['roleId' => $conditional->id])->assertCreated();
    }

    public function test_scope_codes_are_hashed_single_use_and_bound_to_a_users_own_assignment(): void
    {
        [$institution, $offering] = $this->offering();
        $admin = $this->user(['is_admin' => true]);
        $student = $this->user();
        $role = $this->role($institution, ['offering.view'], 'OFFERING');
        $assignment = $this->assignment($student, $role);
        $code = $this->actingAs($admin)->postJson('/api/v1/institutions/'.$institution->id.'/scope-codes', ['offeringId' => $offering->id])->assertCreated()->assertJsonMissingPath('data.codeHash')->json('data.code');
        $this->assertDatabaseHas('scope_assignment_codes', ['code_hash' => hash('sha256', $code)]);
        $other = $this->user();
        $this->actingAs($other)->postJson('/api/v1/scope-codes/redeem', ['code' => $code, 'roleAssignmentId' => $assignment->id])->assertNotFound();
        $this->actingAs($student)->postJson('/api/v1/scope-codes/redeem', ['code' => $code, 'roleAssignmentId' => $assignment->id])->assertCreated();
        $this->postJson('/api/v1/scope-codes/redeem', ['code' => $code, 'roleAssignmentId' => $assignment->id])->assertStatus(409);
    }

    public function test_scope_codes_cannot_expand_a_role_above_the_issuers_permissions(): void
    {
        [$institution, $offering] = $this->offering();
        $issuer = $this->user();
        $issuerAssignment = $this->assignment($issuer, $this->role($institution, ['scope_assignment_code.create', 'offering.view'], 'OFFERING'));
        ScopeAssignment::create(['role_assignment_id' => $issuerAssignment->id, 'offering_id' => $offering->id]);
        $student = $this->user();
        $high = $this->assignment($student, $this->role($institution, ['attempt.mark'], 'OFFERING'));
        $code = $this->actingAs($issuer)->postJson('/api/v1/institutions/'.$institution->id.'/scope-codes', ['offeringId' => $offering->id])->assertCreated()->json('data.code');
        $this->actingAs($student)->postJson('/api/v1/scope-codes/redeem', ['code' => $code, 'roleAssignmentId' => $high->id])->assertForbidden();
        $this->assertDatabaseCount('scope_assignments', 1);
    }

    public function test_auto_grant_requires_verified_email_and_scoped_regex_match(): void
    {
        $institution = Institution::factory()->create();
        $role = $this->role($institution, ['offering.view'], 'OFFERING', ['auto_grant_email_regex' => '@school\\.example$']);
        $user = $this->user(['email' => 'student@school.example', 'email_verified_at' => null]);
        $this->actingAs($user)->postJson('/api/v1/institutions/'.$institution->id.'/join')->assertForbidden();
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->postJson('/api/v1/institutions/'.$institution->id.'/join')->assertOk();
        $this->assertDatabaseHas('role_assignments', ['user_id' => $user->id, 'role_id' => $role->id, 'revoked_at' => null]);
        $this->assertDatabaseCount('scope_assignments', 0);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['password' => 'LongPassword123!', 'email_verified_at' => now()], $attributes));
    }

    private function role(Institution $institution, array $permissions, string $scope = 'INSTITUTION', array $attributes = []): Role
    {
        $role = Role::create(array_merge(['institution_id' => $institution->id, 'role_code' => strtolower($scope).':'.strtolower(\Illuminate\Support\Str::random(12)), 'role_name' => 'Test role', 'scope_type' => $scope, 'request_mode' => 'DISABLED'], $attributes));
        $role->permissions()->sync($permissions);
        return $role;
    }

    private function assignment(User $user, Role $role): RoleAssignment
    {
        return RoleAssignment::create(['user_id' => $user->id, 'role_id' => $role->id, 'granted_at' => now()]);
    }

    private function offering(?Institution $institution = null): array
    {
        $institution ??= Institution::factory()->create();
        $subject = $institution->subjects()->create(['subject_code' => 'COMP'.random_int(1000, 9999), 'subject_name' => 'Programming']);
        $term = StudyTerm::firstOrCreate(['institution_id' => $institution->id, 'year' => 2026, 'session' => 1]);
        return [$institution, Offering::create(['institution_id' => $institution->id, 'subject_id' => $subject->id, 'term_id' => $term->id])];
    }
}
