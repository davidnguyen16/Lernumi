<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\Assessment;
use App\Models\CalendarEvent;
use App\Models\Offering;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use App\Models\StudyTerm;
use App\Models\StudyTermPeriod;
use App\Models\User;
use App\Services\PermissionCatalog;
use App\Services\WorkspaceService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::codes() as $code) {
            Permission::query()->updateOrCreate(['code' => $code], ['description' => ucfirst(str_replace(['.', '_'], ' ', $code)).'.']);
        }
        // Production uses an explicitly created administrator, never shared demo credentials.
        if (! app()->environment(['local', 'testing'])) return;

        $institution = Institution::firstOrCreate(
            ['id' => '11111111-1111-4111-8111-111111111111'],
            ['name' => 'Lernumi Demo', 'website' => 'https://example.com'],
        );

        foreach ([
            ['subject_code' => 'COMP1000', 'subject_name' => 'Introduction to Computer Programming'],
            ['subject_code' => 'COMP1010', 'subject_name' => 'Fundamentals of Computer Science'],
        ] as $subject) {
            $institution->subjects()->firstOrCreate(
                ['subject_code' => $subject['subject_code']],
                ['subject_name' => $subject['subject_name'], 'subject_description' => null],
            );
        }

        $password = env('DEMO_PASSWORD', 'LernumiDemo123!');
        $people = [];
        foreach (['admin' => 'Demo Administrator', 'teacher' => 'Demo Teacher', 'student' => 'Demo Student'] as $code => $name) {
            $user = User::query()->firstOrNew(['email' => "{$code}@lernumi.test"]);
            if (! $user->exists) {
                $user->forceFill(['name' => $name, 'password' => $password, 'is_admin' => $code === 'admin', 'is_active' => true, 'email_verified_at' => now()])->save();
            }
            $people[$code] = $user;
        }
        $term = StudyTerm::query()->firstOrCreate(['institution_id' => $institution->id, 'year' => now()->year, 'session' => 1]);
        $start = now()->startOfMonth()->subMonth();
        StudyTermPeriod::query()->firstOrCreate(['study_term_id' => $term->id, 'name' => 'Demo teaching period'], ['type' => 'TEACHING', 'start_at' => $start, 'end_at' => $start->copy()->addMonths(3)]);
        $subject = $institution->subjects()->where('subject_code', 'COMP1000')->firstOrFail();
        $offering = Offering::query()->firstOrCreate(['subject_id' => $subject->id, 'term_id' => $term->id], ['institution_id' => $institution->id]);
        $studentPermissions = ['offering.view', 'assessment.view_published', 'assessment.attempt', 'attempt.view_own', 'tool.launch', 'tool_session.replay_own'];
        $teacherPermissions = ['offering.view', 'offering.update', 'assessment.manage', 'assessment.view_published', 'attempt.view_all', 'attempt.mark', 'attempt.result.finalize', 'tool.launch', 'tool_session.replay_all'];
        foreach (['student' => $studentPermissions, 'teacher' => $teacherPermissions] as $code => $permissions) {
            $canonicalCode = 'offering:'.$code;
            $role = Role::query()->where('institution_id', $institution->id)->where('role_code', $canonicalCode)->first();
            if (! $role) {
                $role = Role::query()->where('institution_id', $institution->id)
                    ->where('role_code', strtoupper($code))->where('scope_type', 'OFFERING')->first();
                if ($role) {
                    // Rename in place: existing assignments and approval history keep their role ID.
                    $role->update(['role_code' => $canonicalCode]);
                } else {
                    $role = Role::query()->create(['institution_id' => $institution->id, 'role_code' => $canonicalCode,
                        'role_name' => ucfirst($code), 'scope_type' => 'OFFERING', 'request_mode' => $code === 'student' ? 'PUBLIC' : 'DISABLED']);
                }
            }
            // If both codes already exist, use the canonical role and retain the legacy history.
            $role->permissions()->sync($permissions);
            $assignment = RoleAssignment::query()->firstOrCreate(['user_id' => $people[$code]->id, 'role_id' => $role->id, 'revoked_at' => null], ['granted_by_id' => $people['admin']->id, 'granted_at' => now()]);
            ScopeAssignment::query()->firstOrCreate(['role_assignment_id' => $assignment->id, 'offering_id' => $offering->id, 'revoked_at' => null], ['granted_by_id' => $people['admin']->id, 'granted_at' => now()]);
        }
        Assessment::query()->firstOrCreate(['offering_id' => $offering->id, 'name' => 'Demo programming assignment'], ['description' => 'Create a workspace, work on your submission, then submit it for marking.', 'weight' => 100, 'hurdle' => false, 'is_group' => false, 'publish_at' => now()->subDay(), 'due_at' => now()->addWeeks(2), 'expected_due' => 'Demo deadline']);
        if (! \App\Models\PersonalWorkspace::query()->where('user_id', $people['student']->id)->exists()) {
            app(WorkspaceService::class)->createPersonal($people['student'], 'Personal notes');
        }
        CalendarEvent::query()->firstOrCreate(['user_id' => $people['student']->id, 'title' => 'Demo study session'], ['note' => 'Example event for API integration.', 'start_at' => now()->addDay()->startOfHour(), 'end_at' => now()->addDay()->startOfHour()->addHour(), 'offering_id' => $offering->id]);
        $this->call(DemoToolSeeder::class);
    }
}
