<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Offering;
use App\Models\Tool;
use App\Models\User;
use App\Services\ToolPackageService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

/** A small real package for exercising the local runtime, never production data. */
class DemoToolSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) return;

        $admin = User::query()->where('email', 'admin@lernumi.test')->firstOrFail();
        $offering = Offering::query()->where('institution_id', '11111111-1111-4111-8111-111111111111')
            ->whereHas('subject', fn ($query) => $query->where('subject_code', 'COMP1000'))
            ->whereHas('term', fn ($query) => $query->where('year', now()->year)->where('session', 1))
            ->firstOrFail();
        $tool = Tool::query()->firstOrCreate(['code' => 'demo-api-probe'], [
            'name' => 'Demo API probe',
            'description' => 'A small local tool that displays its authorized runtime context.',
        ]);

        if (! $tool->versions()->where('version', '1.0.0')->exists()) {
            $archivePath = $this->createArchive();
            try {
                app(ToolPackageService::class)->upload($admin, $tool,
                    new UploadedFile($archivePath, 'demo-api-probe-1.0.0.zip', 'application/zip', null, true),
                    'Local runtime connectivity fixture.', $tool->active_version_id === null);
            } finally {
                if (is_file($archivePath)) unlink($archivePath);
            }
        }
        // Existing immutable versions and the administrator's active-version choice are preserved.

        $assessment = Assessment::query()->firstOrCreate([
            'offering_id' => $offering->id, 'name' => 'Demo tool practice',
        ], [
            'description' => 'Open the demo tool to inspect your workspace, mode and capabilities.',
            'weight' => 0, 'hurdle' => false, 'is_group' => false,
            'publish_at' => now()->subDay(), 'due_at' => null, 'expected_due' => 'Local practice',
        ]);
        $assessment->allowedTools()->syncWithoutDetaching([$tool->id]);
    }

    private function createArchive(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lernumi-demo-tool-');
        if ($path === false) throw new RuntimeException('Could not create the demo package archive.');
        $archive = new ZipArchive;
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            unlink($path);
            throw new RuntimeException('Could not open the demo package archive.');
        }
        try {
            foreach (['tool.json', 'index.html', 'app.js'] as $name) {
                if (! $archive->addFile(resource_path('demo-tool/'.$name), $name)) {
                    throw new RuntimeException('Could not add a demo tool file to the package.');
                }
            }
            if (! $archive->close()) throw new RuntimeException('Could not finish the demo package archive.');

            return $path;
        } catch (\Throwable $error) {
            // ZipArchive also releases its handle on destruction when construction fails.
            unset($archive);
            if (is_file($path)) unlink($path);
            throw $error;
        }
    }
}
