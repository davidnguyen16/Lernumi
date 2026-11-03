<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Assessment;
use App\Models\Offering;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use App\Models\StudyTerm;
use App\Models\Tool;
use App\Models\User;
use App\Services\ToolPackageService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4]);
        Storage::fake('local');
    }

    public function test_legacy_demo_roles_are_renamed_in_place_without_resetting_accounts_or_assignments(): void
    {
        [$institution, $offering] = $this->demoOffering();
        $student = User::factory()->create(['email' => 'student@lernumi.test', 'name' => 'Existing Student', 'password' => 'KeepPassword456!']);
        $passwordHash = $student->password;
        $legacyStudent = $this->role($institution, 'STUDENT');
        $legacyTeacher = $this->role($institution, 'TEACHER');
        $active = RoleAssignment::create(['user_id' => $student->id, 'role_id' => $legacyStudent->id]);
        $activeScope = ScopeAssignment::create(['role_assignment_id' => $active->id, 'offering_id' => $offering->id]);
        $history = RoleAssignment::create(['user_id' => $student->id, 'role_id' => $legacyStudent->id, 'revoked_at' => now()->subDay()]);
        $historyScope = ScopeAssignment::create(['role_assignment_id' => $history->id, 'offering_id' => $offering->id, 'revoked_at' => now()->subDay()]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('roles', ['id' => $legacyStudent->id, 'role_code' => 'offering:student']);
        $this->assertDatabaseHas('roles', ['id' => $legacyTeacher->id, 'role_code' => 'offering:teacher']);
        $this->assertDatabaseMissing('roles', ['institution_id' => $institution->id, 'role_code' => 'STUDENT']);
        $this->assertDatabaseMissing('roles', ['institution_id' => $institution->id, 'role_code' => 'TEACHER']);
        $this->assertDatabaseHas('role_assignments', ['id' => $active->id, 'role_id' => $legacyStudent->id, 'revoked_at' => null]);
        $this->assertDatabaseHas('scope_assignments', ['id' => $activeScope->id, 'role_assignment_id' => $active->id, 'revoked_at' => null]);
        $this->assertNotNull($history->fresh()->revoked_at);
        $this->assertNotNull($historyScope->fresh()->revoked_at);
        $this->assertSame($passwordHash, $student->fresh()->password);
        $this->assertSame('Existing Student', $student->fresh()->name);
        $this->assertDatabaseCount('roles', 2);
        $this->assertDatabaseCount('role_assignments', 3);
        $this->assertDatabaseCount('scope_assignments', 3);
        $this->assertDatabaseCount('users', 3);

        $admin = User::where('email', 'admin@lernumi.test')->firstOrFail();
        $this->actingAs($admin)->patchJson('/api/v1/institutions/'.$institution->id.'/roles/'.$legacyStudent->id,
            ['roleName' => 'Renamed demo student'])->assertOk()->assertJsonPath('data.roleCode', 'offering:student');
    }

    public function test_existing_canonical_demo_roles_are_preferred_and_legacy_history_is_retained(): void
    {
        [$institution, $offering] = $this->demoOffering();
        $canonicalStudent = $this->role($institution, 'offering:student');
        $canonicalTeacher = $this->role($institution, 'offering:teacher');
        $legacyStudent = $this->role($institution, 'STUDENT');
        $legacyTeacher = $this->role($institution, 'TEACHER');
        $member = User::factory()->create();
        $assignment = RoleAssignment::create(['user_id' => $member->id, 'role_id' => $legacyStudent->id]);
        $scope = ScopeAssignment::create(['role_assignment_id' => $assignment->id, 'offering_id' => $offering->id]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        foreach (['student' => $canonicalStudent, 'teacher' => $canonicalTeacher] as $code => $role) {
            $user = User::where('email', $code.'@lernumi.test')->firstOrFail();
            $this->assertDatabaseHas('role_assignments', ['user_id' => $user->id, 'role_id' => $role->id, 'revoked_at' => null]);
        }
        $this->assertDatabaseHas('roles', ['id' => $legacyStudent->id, 'role_code' => 'STUDENT']);
        $this->assertDatabaseHas('roles', ['id' => $legacyTeacher->id, 'role_code' => 'TEACHER']);
        $this->assertDatabaseHas('role_assignments', ['id' => $assignment->id, 'role_id' => $legacyStudent->id, 'revoked_at' => null]);
        $this->assertDatabaseHas('scope_assignments', ['id' => $scope->id, 'role_assignment_id' => $assignment->id, 'revoked_at' => null]);
        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('role_assignments', 3);
        $this->assertDatabaseCount('scope_assignments', 3);
    }

    public function test_seeding_does_not_rename_roles_in_another_institution(): void
    {
        $foreign = Institution::factory()->create();
        $role = $this->role($foreign, 'STUDENT');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'institution_id' => $foreign->id, 'role_code' => 'STUDENT']);
        $this->assertDatabaseHas('roles', ['institution_id' => '11111111-1111-4111-8111-111111111111', 'role_code' => 'offering:student']);
    }

    public function test_demo_tool_is_a_stored_immutable_package_and_repeat_seed_preserves_assessments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tool = Tool::where('code', 'demo-api-probe')->firstOrFail();
        $version = $tool->activeVersion;
        $this->assertNotNull($version);
        $this->assertSame('1.0.0', $version->version);
        $this->assertSame('index.html', $version->entry_path);
        $this->assertSame('demo-api-probe', $version->manifest['code']);
        $this->assertTrue(Storage::disk('local')->exists($version->archive_key));
        $this->assertSame($version->sha256, hash('sha256', Storage::disk('local')->get($version->archive_key)));
        foreach (['tool.json', 'index.html', 'app.js'] as $file) {
            $this->assertTrue(Storage::disk('local')->exists($version->storage_prefix.'/'.$file));
        }
        $practice = Assessment::where('name', 'Demo tool practice')->firstOrFail();
        $original = Assessment::where('name', 'Demo programming assignment')->firstOrFail();
        $this->assertSame(0.0, $practice->weight);
        $this->assertTrue($practice->publish_at->lessThanOrEqualTo(now()));
        $this->assertSame([$tool->id], $practice->allowedTools()->pluck('tools.id')->all());
        $this->assertSame(100.0, $original->weight);
        $this->assertSame(0, $original->allowedTools()->count());

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('tools', 1);
        $this->assertDatabaseCount('tool_versions', 1);
        $this->assertDatabaseCount('assessments', 2);
        $this->assertSame($version->id, $tool->fresh()->active_version_id);
        $this->assertSame([$tool->id], $practice->fresh()->allowedTools()->pluck('tools.id')->all());
        $this->assertDatabaseHas('assessments', ['id' => $original->id, 'weight' => 100]);
    }

    public function test_reseeding_keeps_a_newer_version_activated_by_an_administrator(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tool = Tool::where('code', 'demo-api-probe')->firstOrFail();
        $admin = User::where('email', 'admin@lernumi.test')->firstOrFail();
        $newVersion = app(ToolPackageService::class)->upload($admin, $tool, $this->package('1.1.0'), 'Admin update', true);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($newVersion->id, $tool->fresh()->active_version_id);
        $this->assertDatabaseCount('tool_versions', 2);
        $this->assertDatabaseHas('tool_versions', ['tool_id' => $tool->id, 'version' => '1.0.0']);
    }

    public function test_production_seeding_does_not_create_demo_rows_or_files(): void
    {
        $this->app->instance('env', 'production');
        try {
            (new DatabaseSeeder)->run();
            (new DemoToolSeeder)->run();
            foreach (['users', 'institutions', 'subjects', 'study_terms', 'offerings', 'roles', 'assessments', 'tools', 'tool_versions'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    private function package(string $version): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'lernumi-demo-test-');
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('tool.json', json_encode(['schemaVersion' => 1, 'code' => 'demo-api-probe', 'version' => $version, 'entry' => 'index.html'], JSON_THROW_ON_ERROR));
        $archive->addFromString('index.html', '<!doctype html><title>Admin version</title>');
        $archive->close();
        try {
            return UploadedFile::fake()->createWithContent('tool.zip', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    private function role(Institution $institution, string $code): Role
    {
        return Role::create(['institution_id' => $institution->id, 'role_code' => $code, 'role_name' => $code, 'scope_type' => 'OFFERING', 'request_mode' => 'DISABLED']);
    }

    private function demoOffering(): array
    {
        $institution = Institution::factory()->create(['id' => '11111111-1111-4111-8111-111111111111']);
        $subject = $institution->subjects()->create(['subject_code' => 'COMP1000', 'subject_name' => 'Existing Programming']);
        $term = StudyTerm::create(['institution_id' => $institution->id, 'year' => now()->year, 'session' => 1]);
        $offering = Offering::create(['institution_id' => $institution->id, 'subject_id' => $subject->id, 'term_id' => $term->id]);

        return [$institution, $offering];
    }
}
