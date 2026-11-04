#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\Offering;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Local browser-test fixture only. Never run against production or reset a database. */
final class E2eFixtureFailure extends RuntimeException {}

function fixtureAssert(bool $condition, string $message): void
{
    if (! $condition) throw new E2eFixtureFailure($message);
}

try {
    fixtureAssert(PHP_SAPI === 'cli' && count($argv) === 3, 'Usage: e2e-fixture.php create|cleanup <run-uuid>');
    [$script, $action, $runId] = $argv;
    require dirname(__DIR__).'/vendor/autoload.php';
    fixtureAssert(in_array($action, ['create', 'cleanup'], true) && Str::isUuid($runId), 'Use a valid fixture action and run UUID.');
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    fixtureAssert($app->environment(['local', 'testing']), 'E2E fixtures require APP_ENV=local or testing.');
    $connection = DB::connection();
    fixtureAssert($connection->getDriverName() === 'pgsql'
        && in_array($connection->getDatabaseName(), ['lernumi', 'lernumi_testing'], true)
        && in_array($connection->getConfig('host'), ['postgres', 'localhost', '127.0.0.1', '::1'], true),
        'E2E fixtures require the local Lernumi PostgreSQL database.');
    $appUrl = parse_url((string) config('app.url'));
    fixtureAssert(is_array($appUrl) && in_array($appUrl['host'] ?? '', ['localhost', '127.0.0.1', '::1', '[::1]'], true), 'E2E fixtures require a loopback APP_URL.');
    $disk = Storage::disk('local');
    $manifestKey = 'e2e-fixtures/'.$runId.'.json';
    $name = 'E2E assessment '.$runId;

    if ($action === 'create') {
        fixtureAssert(! $disk->exists($manifestKey) && ! Assessment::where('name', $name)->exists(), 'This fixture run already exists.');
        $student = User::where('email', 'student@lernumi.test')->where('is_active', true)->first();
        $teacher = User::where('email', 'teacher@lernumi.test')->where('is_active', true)->first();
        fixtureAssert($student !== null && $teacher !== null && ! $student->is_admin && ! $teacher->is_admin, 'Seed the local demo student and teacher before running browser tests.');
        $offerings = Offering::whereHas('institution', fn ($query) => $query->where('name', 'Lernumi Demo'))
            ->whereHas('subject', fn ($query) => $query->where('subject_code', 'COMP1000'))
            ->whereHas('term', fn ($query) => $query->where('year', now()->year)->where('session', 1))->get();
        fixtureAssert($offerings->count() === 1, 'Exactly one seeded COMP1000 offering is required.');
        $offering = $offerings->sole();
        $access = app(AccessService::class);
        foreach (['offering.view', 'assessment.view_published', 'attempt.start', 'attempt.submit', 'attempt.view_own'] as $permission) {
            fixtureAssert($access->can($student, $permission, $offering), 'The demo student lacks assessment access.');
        }
        foreach (['offering.view', 'assessment.view', 'attempt.mark', 'attempt.result.finalize'] as $permission) {
            fixtureAssert($access->can($teacher, $permission, $offering), 'The demo teacher lacks marking access.');
        }
        $manifest = DB::transaction(function () use ($disk, $manifestKey, $runId, $name, $offering, $student, $teacher): array {
            $assessment = Assessment::create(['offering_id' => $offering->id, 'name' => $name,
                'description' => 'Temporary browser-test submission. Removed by its owning E2E run.',
                'weight' => 0, 'hurdle' => false, 'is_group' => false,
                'publish_at' => now()->subMinute(), 'due_at' => now()->addDay()]);
            $manifest = ['runId' => $runId, 'assessmentId' => $assessment->id, 'assessmentName' => $name,
                'institutionId' => $offering->institution_id, 'offeringId' => $offering->id,
                'studentId' => $student->id, 'teacherId' => $teacher->id];
            fixtureAssert($disk->put($manifestKey, json_encode($manifest, JSON_THROW_ON_ERROR)), 'Could not record fixture ownership.');
            return $manifest;
        });
        // Return identifiers only: passwords, cookies and runtime tokens are never logged.
        echo json_encode(array_intersect_key($manifest, array_flip(['runId', 'assessmentId', 'assessmentName', 'institutionId', 'offeringId'])), JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }

    if (! $disk->exists($manifestKey)) {
        echo json_encode(['runId' => $runId, 'cleaned' => true], JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }
    $manifest = json_decode($disk->get($manifestKey), true, 512, JSON_THROW_ON_ERROR);
    fixtureAssert(is_array($manifest) && ($manifest['runId'] ?? null) === $runId && ($manifest['assessmentName'] ?? null) === $name, 'Fixture ownership manifest mismatch.');
    foreach (['assessmentId', 'institutionId', 'offeringId', 'studentId', 'teacherId'] as $field) {
        fixtureAssert(is_string($manifest[$field] ?? null) && Str::isUuid($manifest[$field]), 'Fixture ownership contains an invalid identifier.');
    }
    $blobKeys = DB::transaction(function () use ($manifest, $name, $disk): array {
        $assessment = Assessment::whereKey($manifest['assessmentId'])->lockForUpdate()->first();
        if (! $assessment) return [];
        fixtureAssert($assessment->name === $name && $assessment->offering_id === $manifest['offeringId'] && $assessment->weight === 0.0 && ! $assessment->is_group,
            'Fixture assessment changed unexpectedly; no records were removed.');
        fixtureAssert($assessment->offering->institution_id === $manifest['institutionId']
            && $assessment->offering->institution->name === 'Lernumi Demo'
            && $assessment->offering->subject->subject_code === 'COMP1000', 'Fixture offering ownership mismatch.');
        fixtureAssert(User::whereKey($manifest['studentId'])->where('email', 'student@lernumi.test')->exists()
            && User::whereKey($manifest['teacherId'])->where('email', 'teacher@lernumi.test')->exists(), 'Fixture demo account ownership mismatch.');
        $attempts = DB::table('assessment_attempts')->where('assessment_id', $assessment->id)->lockForUpdate()->get();
        fixtureAssert($attempts->every(fn ($attempt) => $attempt->user_id === $manifest['studentId'] && $attempt->group_id === null)
            && ! $assessment->groups()->exists(), 'Unexpected participants used the temporary assessment; cleanup stopped.');
        $attemptIds = $attempts->pluck('id');
        fixtureAssert(! DB::table('assessment_attempt_marks')->whereIn('attempt_id', $attemptIds)->where('marked_by_id', '<>', $manifest['teacherId'])->exists()
            && ! DB::table('assessment_attempt_results')->whereIn('attempt_id', $attemptIds)->where('finalised_by_id', '<>', $manifest['teacherId'])->exists(), 'Unexpected markers used the fixture; cleanup stopped.');
        $workspaceIds = DB::table('assignment_workspaces')->whereIn('attempt_id', $attemptIds)->pluck('workspace_id');
        fixtureAssert(! DB::table('personal_workspaces')->whereIn('workspace_id', $workspaceIds)->exists(), 'Fixture workspace is unexpectedly shared with personal data.');
        $blobKeys = [];
        foreach ($workspaceIds as $workspaceId) {
            fixtureAssert(Str::isUuid($workspaceId), 'Invalid fixture workspace identifier.');
            foreach ($disk->allFiles('documents/'.$workspaceId) as $key) {
                $parts = explode('/', $key);
                fixtureAssert(count($parts) === 4 && $parts[0] === 'documents' && $parts[1] === $workspaceId
                    && Str::isUuid($parts[2]) && preg_match('/^[a-f0-9]{64}$/D', $parts[3]) === 1, 'Unexpected fixture content path; cleanup stopped.');
                $blobKeys[] = $key;
            }
        }
        // Keep every delete constrained to this manifest's assessment or its owned workspaces.
        DB::table('assessment_attempt_results')->whereIn('attempt_id', $attemptIds)->delete();
        DB::table('assessment_attempt_marks')->whereIn('attempt_id', $attemptIds)->update(['supersedes_mark_id' => null]);
        DB::table('assessment_attempt_marks')->whereIn('attempt_id', $attemptIds)->delete();
        DB::table('workspaces')->whereIn('id', $workspaceIds)->delete();
        DB::table('assessment_attempts')->where('assessment_id', $assessment->id)->delete();
        $assessment->delete();
        return $blobKeys;
    });
    foreach ($blobKeys as $key) fixtureAssert($disk->delete($key), 'Could not remove a temporary fixture blob.');
    fixtureAssert($disk->delete($manifestKey), 'Could not remove the completed fixture manifest.');
    echo json_encode(['runId' => $runId, 'cleaned' => true], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, ($error instanceof E2eFixtureFailure ? $error->getMessage() : 'E2E fixture operation failed. Check the local API configuration and logs.').PHP_EOL);
    exit(1);
}
