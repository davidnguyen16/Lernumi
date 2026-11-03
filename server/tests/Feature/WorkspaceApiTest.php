<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Document;
use App\Models\Institution;
use App\Models\Offering;
use App\Models\StudyTerm;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_personal_workspace_crud_is_private_and_paginated(): void
    {
        $owner = $this->user();
        $created = $this->actingAs($owner)->postJson('/api/v1/workspaces', ['name' => 'My notes'])->assertCreated()->assertJsonPath('data.kind', 'PERSONAL');
        $id = $created->json('data.id');
        $this->getJson('/api/v1/workspaces')->assertOk()->assertJsonPath('meta.total', 1);
        $this->patchJson('/api/v1/workspaces/'.$id, ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->actingAs($this->user())->getJson('/api/v1/workspaces/'.$id)->assertNotFound();
        $this->getJson('/api/v1/workspaces')->assertOk()->assertJsonPath('meta.total', 0);
        $this->deleteJson('/api/v1/workspaces/'.$id)->assertNotFound();
        $this->actingAs($owner)->deleteJson('/api/v1/workspaces/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('workspaces', ['id' => $id]);
    }

    public function test_document_content_preserves_whitespace_and_uses_optimistic_concurrency(): void
    {
        $workspace = $this->workspace();
        $path = '/api/v1/workspaces/'.$workspace->id.'/documents';
        $content = "  const answer = 42;\n\n";
        $created = $this->postJson($path, ['name' => 'main.js', 'entryKind' => 'FILE', 'content' => $content])->assertCreated();
        $id = $created->json('data.id');
        $hash = hash('sha256', $content);
        $created->assertJsonPath('data.contentHash', $hash)->assertJsonPath('data.sizeBytes', strlen($content));
        $this->get($path.'/'.$id)->assertOk()->assertContent($content)->assertHeader('ETag', '"'.$hash.'"');
        $this->call('PUT', $path.'/'.$id.'/content', [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'changed')->assertStatus(428);
        $this->call('PUT', $path.'/'.$id.'/content', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_IF_MATCH' => '"stale"'], 'changed')->assertStatus(412);
        $this->call('PUT', $path.'/'.$id.'/content', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_IF_MATCH' => '"'.$hash.'"'], " updated \n")
            ->assertOk()->assertHeader('ETag', '"'.hash('sha256', " updated \n").'"');
        $this->get($path.'/'.$id)->assertContent(" updated \n");
        $currentHash = hash('sha256', " updated \n");
        $this->call('PUT', $path.'/'.$id.'/content', [], [], [], ['CONTENT_TYPE' => 'text/'.str_repeat('x', 151), 'HTTP_IF_MATCH' => '"'.$currentHash.'"'], 'invalid type')->assertUnprocessable();
        $this->get($path.'/'.$id)->assertContent(" updated \n");
    }

    public function test_folder_names_are_unique_and_moves_cannot_create_cycles(): void
    {
        $workspace = $this->workspace();
        $path = '/api/v1/workspaces/'.$workspace->id.'/documents';
        $folder = $this->postJson($path, ['name' => 'src', 'entryKind' => 'FOLDER'])->assertCreated()->json('data.id');
        $this->postJson($path, ['name' => 'src', 'entryKind' => 'FOLDER'])->assertUnprocessable();
        $this->postJson($path, ['name' => 'src', 'entryKind' => 'FOLDER', 'reuseExisting' => true])->assertCreated()->assertJsonPath('data.id', $folder);
        $child = $this->postJson($path, ['name' => 'nested', 'entryKind' => 'FOLDER', 'parentFolderId' => $folder])->assertCreated()->json('data.id');
        $this->patchJson($path.'/'.$folder, ['parentFolderId' => $child])->assertUnprocessable()->assertJsonValidationErrors('parentFolderId');
        $this->patchJson($path.'/'.$folder, ['parentFolderId' => $folder])->assertUnprocessable();
        $this->postJson($path, ['name' => '../secret', 'entryKind' => 'FILE'])->assertUnprocessable();
        $this->getJson($path.'?parentFolderId='.Str::uuid())->assertUnprocessable();
        $this->deleteJson($path.'/'.$folder)->assertNoContent();
        $this->assertDatabaseMissing('documents', ['id' => $child]);
    }

    public function test_document_parent_and_identifiers_cannot_cross_workspaces(): void
    {
        $owner = $this->user();
        $first = $this->workspace($owner);
        $second = $this->workspace($owner);
        $firstPath = '/api/v1/workspaces/'.$first->id.'/documents';
        $secondPath = '/api/v1/workspaces/'.$second->id.'/documents';
        $folder = $this->postJson($firstPath, ['name' => 'folder', 'entryKind' => 'FOLDER'])->assertCreated()->json('data.id');
        $file = $this->postJson($secondPath, ['name' => 'file.txt', 'entryKind' => 'FILE'])->assertCreated()->json('data.id');
        $this->postJson($secondPath, ['name' => 'wrong.txt', 'parentFolderId' => $folder])->assertUnprocessable();
        $this->patchJson($secondPath.'/'.$file, ['parentFolderId' => $folder])->assertUnprocessable();
        $this->deleteJson($firstPath.'/'.$file)->assertNotFound();
        $this->get($firstPath.'/'.$file)->assertNotFound();
        $this->assertDatabaseHas('documents', ['id' => $file, 'workspace_id' => $second->id]);
    }

    public function test_uploaded_files_are_private_and_bulk_delete_validates_every_identifier(): void
    {
        $owner = $this->user();
        $workspace = $this->workspace($owner);
        $path = '/api/v1/workspaces/'.$workspace->id.'/documents';
        $this->post($path, ['name' => 'bad-folder', 'entryKind' => 'FOLDER', 'file' => UploadedFile::fake()->createWithContent('notes.txt', 'lost bytes')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->postJson($path, ['name' => 'bad-folder', 'entryKind' => 'FOLDER', 'content' => 'lost bytes'])->assertUnprocessable();
        $id = $this->post($path, ['file' => UploadedFile::fake()->createWithContent('notes.txt', 'private notes')], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->get($path.'/'.$id)->assertContent('private notes');
        $this->deleteJson($path, ['documentIds' => [$id, (string) Str::uuid()]])->assertNotFound();
        $this->assertDatabaseHas('documents', ['id' => $id]);
        $this->actingAs($this->user())->get($path.'/'.$id, ['Accept' => 'application/json'])->assertNotFound();
        $this->actingAs($owner)->deleteJson($path, ['documentIds' => [$id]])->assertNoContent();
    }

    public function test_submitted_assignment_documents_remain_read_only_at_the_service_boundary(): void
    {
        $owner = $this->user(true);
        $institution = Institution::create(['name' => 'Workspace University']);
        $subject = $institution->subjects()->create(['subject_code' => 'COMP1000', 'subject_name' => 'Programming']);
        $term = StudyTerm::create(['institution_id' => $institution->id, 'year' => 2026, 'session' => 1]);
        $offering = Offering::create(['institution_id' => $institution->id, 'subject_id' => $subject->id, 'term_id' => $term->id]);
        $assessment = Assessment::create(['offering_id' => $offering->id, 'name' => 'Assignment', 'weight' => 100, 'publish_at' => now()->subDay()]);
        $attempt = AssessmentAttempt::create(['user_id' => $owner->id, 'assessment_id' => $assessment->id, 'start_at' => now()]);
        $workspace = app(WorkspaceService::class)->forAttempt($attempt);
        $path = '/api/v1/workspaces/'.$workspace->id.'/documents';
        $id = $this->actingAs($owner)->postJson($path, ['name' => 'submission.txt', 'content' => 'answer'])->assertCreated()->json('data.id');
        $attempt->update(['submit_at' => now()]);
        $this->getJson('/api/v1/workspaces/'.$workspace->id)->assertOk()->assertJsonPath('data.readOnly', true);
        $this->postJson($path, ['name' => 'late.txt'])->assertForbidden();
        $this->patchJson($path.'/'.$id, ['name' => 'changed.txt'])->assertForbidden();
        $this->deleteJson($path.'/'.$id)->assertForbidden();
        $this->get($path.'/'.$id)->assertContent('answer');
    }

    public function test_institution_logo_upload_and_removal_require_permission(): void
    {
        $institution = Institution::create(['name' => 'Logo university']);
        $path = '/api/v1/institutions/'.$institution->id.'/logo';
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9xkAAAAASUVORK5CYII=');
        $this->actingAs($this->user())->post($path, ['logo' => UploadedFile::fake()->createWithContent('logo.png', $bytes)], ['Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($this->user(true))->post($path, ['logo' => UploadedFile::fake()->createWithContent('logo.png', $bytes)], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.logoUrl', url($path));
        $logoResponse = $this->get($path)->assertOk();
        $this->assertSame($bytes, $logoResponse->baseResponse->getFile()->getContent());
        $this->deleteJson($path)->assertNoContent();
        $this->get($path, ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_workspace_list_requires_authentication_and_valid_pagination(): void
    {
        $this->getJson('/api/v1/workspaces')->assertUnauthorized();
        $this->actingAs($this->user())->getJson('/api/v1/workspaces?per_page=101')->assertUnprocessable();
        $this->getJson('/api/v1/workspaces?page=0')->assertUnprocessable();
    }

    private function user(bool $admin = false): User
    {
        return User::forceCreate(['name' => 'Workspace user', 'email' => Str::uuid().'@example.test', 'password' => 'WorkspaceTest123!', 'is_admin' => $admin]);
    }

    private function workspace(?User $user = null): \App\Models\Workspace
    {
        $user ??= $this->user();
        $this->actingAs($user);

        return app(WorkspaceService::class)->createPersonal($user, 'Notes');
    }
}
