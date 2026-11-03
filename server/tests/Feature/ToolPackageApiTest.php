<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class ToolPackageApiTest extends TestCase
{
    use RefreshDatabase;
    private array $archives = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->archives as $path) if (is_file($path)) unlink($path);
        parent::tearDown();
    }

    public function test_admin_can_upload_immutable_versions_activate_and_download_original_zip(): void
    {
        $this->actingAs($this->user(true));
        $tool = $this->postJson('/api/v1/admin/tools', ['code' => 'code-editor', 'name' => 'Code editor'])->assertCreated()->json('data.id');
        $path = '/api/v1/admin/tools/'.$tool.'/versions';
        $created = $this->post($path, ['package' => $this->package()], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.version', '1.0.0');
        $versionId = $created->json('data.id');
        $this->assertDatabaseHas('tools', ['id' => $tool, 'active_version_id' => $versionId]);
        $this->get($path.'/'.$versionId.'/download')->assertOk();
        $this->post($path, ['package' => $this->package()], ['Accept' => 'application/json'])->assertUnprocessable();
        $new = $this->post($path, ['package' => $this->package(['version' => '1.1.0']), 'activate' => false], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('tools', ['id' => $tool, 'active_version_id' => $versionId]);
        $this->postJson($path.'/'.$new.'/activate')->assertOk()->assertJsonPath('data.activeVersionId', $new);
        $this->patchJson('/api/v1/admin/tools/'.$tool, ['code' => 'changed'])->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/tools/'.$tool)->assertConflict();
        $this->assertDatabaseCount('tool_versions', 2);
    }

    public function test_non_admin_cannot_create_tools_or_read_private_package_archives(): void
    {
        $this->actingAs($this->user());
        $this->getJson('/api/v1/admin/tools')->assertForbidden();
        $this->postJson('/api/v1/admin/tools', ['code' => 'code-editor', 'name' => 'Editor'])->assertForbidden();
        $tool = Tool::create(['code' => 'draft-tool', 'name' => 'Draft']);
        $this->getJson('/api/v1/tools/'.$tool->id)->assertNotFound();
        $this->getJson('/api/v1/admin/tools/'.$tool->id.'/versions')->assertForbidden();
    }

    public function test_package_paths_symlinks_and_manifest_identity_are_validated_before_activation(): void
    {
        $this->actingAs($this->user(true));
        $tool = Tool::create(['code' => 'code-editor', 'name' => 'Editor']);
        $path = '/api/v1/admin/tools/'.$tool->id.'/versions';
        foreach ([
            $this->package([], ['../escape.js' => 'unsafe']),
            $this->package([], ['.env' => 'unsafe']),
            $this->package(['code' => 'other-tool']),
            $this->package(['entry' => 'missing.html']),
            $this->package(['version' => 'not-semver']),
            $this->package(['schemaVersion' => 2]),
        ] as $archive) {
            $this->post($path, ['package' => $archive], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('package');
        }
        $this->assertDatabaseCount('tool_versions', 0);
        $this->assertDatabaseHas('tools', ['id' => $tool->id, 'active_version_id' => null]);
    }

    public function test_versions_cannot_be_activated_or_downloaded_through_another_tool(): void
    {
        $this->actingAs($this->user(true));
        $first = Tool::create(['code' => 'code-editor', 'name' => 'First']);
        $second = Tool::create(['code' => 'other-tool', 'name' => 'Second']);
        $version = $this->post('/api/v1/admin/tools/'.$first->id.'/versions', ['package' => $this->package()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/tools/'.$second->id.'/versions/'.$version.'/activate')->assertNotFound();
        $this->getJson('/api/v1/admin/tools/'.$second->id.'/versions/'.$version.'/download')->assertNotFound();
    }

    private function user(bool $admin = false): User
    {
        return User::forceCreate(['name' => 'Tool admin', 'email' => Str::uuid().'@example.test', 'password' => 'ToolPackageTest123!', 'is_admin' => $admin]);
    }

    private function package(array $manifestChanges = [], array $extraFiles = []): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'lernumi-test-');
        $this->archives[] = $path;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('tool.json', json_encode(array_replace(['schemaVersion' => 1, 'code' => 'code-editor', 'version' => '1.0.0', 'entry' => 'index.html'], $manifestChanges), JSON_THROW_ON_ERROR));
        $zip->addFromString('index.html', '<!doctype html><title>Editor</title>');
        foreach ($extraFiles as $name => $content) $zip->addFromString($name, $content);
        $zip->close();

        return new UploadedFile($path, 'tool.zip', 'application/zip', null, true);
    }
}
