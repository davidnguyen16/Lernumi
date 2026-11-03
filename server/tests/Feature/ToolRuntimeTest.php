<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssignmentWorkSession;
use App\Models\Institution;
use App\Models\Offering;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ScopeAssignment;
use App\Models\StudyTerm;
use App\Models\Tool;
use App\Models\ToolRuntimeSession;
use App\Models\ToolVersion;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\ToolRuntimeService;
use App\Services\WorkspaceService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToolRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['tools.runtime_origin' => 'http://127.0.0.1:8000', 'tools.platform_origin' => 'http://localhost:3000', 'tools.token_secret' => str_repeat('s', 64), 'tools.token_ttl' => 300]);
    }

    public function test_launch_requires_platform_auth_and_runtime_api_rejects_platform_cookies(): void
    {
        $this->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'PERSONAL'])->assertUnauthorized();
        $user = $this->user();
        $this->actingAs($user)->getJson($this->api('/context'))->assertUnauthorized()->assertJsonPath('error.code', 'AUTHENTICATION_REQUIRED');
        $this->getJson($this->api('/context'), ['Authorization' => 'Bearer invalid'])->assertUnauthorized();
    }

    public function test_personal_session_has_files_without_recording_or_replay(): void
    {
        [$user, $tool, $version, $workspace, $grant] = $this->personal();
        $this->assertSame(['files:read', 'files:write'], $grant['session']['capabilities']);
        $this->assertStringStartsWith('http://127.0.0.1:8000/tool-runtime/bootstrap#lernumi_token=', $grant['runtimeUrl']);
        $this->getJson($this->api('/context'), $this->bearer($grant))->assertOk()->assertJsonPath('workspace.kind', 'PERSONAL')
            ->assertJsonPath('tool.version.id', $version->id)->assertJsonPath('user.id', $user->id);
        $this->getJson($this->api('/events/checkpoint'), $this->bearer($grant))->assertForbidden();
        $this->getJson($this->api('/replay/sessions'), $this->bearer($grant))->assertForbidden();
        $this->assertDatabaseCount('assignment_work_sessions', 0);
    }

    public function test_runtime_hostname_boundary_is_case_insensitive(): void
    {
        config(['tools.runtime_origin' => 'https://TOOLS.example.test', 'tools.platform_origin' => 'https://APP.example.test']);
        $this->getJson('https://tools.example.test/api/v1/health')->assertNotFound();
        $this->postJson('https://tools.example.test/api/tool/v1/sessions', ['mode' => 'PERSONAL'])->assertNotFound();
        $this->getJson('https://app.example.test/api/v1/health')->assertOk();
        $this->get('https://tools.example.test/up')->assertOk();
    }

    public function test_runtime_bearer_mutations_do_not_enter_the_spa_csrf_pipeline(): void
    {
        [, , , , $grant] = $this->personal();
        config(['sanctum.stateful' => ['127.0.0.1:8000']]);
        // Enable real CSRF checks, normally skipped by Laravel's testing environment.
        $this->app->instance('env', 'local');
        $headers = $this->bearer($grant) + ['Origin' => 'http://127.0.0.1:8000'];
        try {
            $this->postJson($this->api('/runtime-auth'), [], $headers)->assertOk();
            $this->postJson($this->api('/token'), [], $headers)->assertOk();
            $this->postJson($this->api('/documents'), ['entryKind' => 'FILE', 'name' => 'browser.txt'], $headers)->assertCreated();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_personal_workspace_ownership_and_active_version_are_required(): void
    {
        [$user, $tool, , $workspace] = $this->personal();
        $this->actingAs($this->user())->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'PERSONAL', 'workspaceId' => $workspace->id, 'toolId' => $tool->id])->assertNotFound();
        $tool->update(['active_version_id' => null]);
        $this->actingAs($user)->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'PERSONAL', 'workspaceId' => $workspace->id, 'toolId' => $tool->id])->assertConflict();
    }

    public function test_different_hostname_is_mandatory_and_not_just_a_different_port(): void
    {
        [$user, $tool, , $workspace] = $this->personal();
        config(['tools.runtime_origin' => 'http://localhost:8000']);
        $this->actingAs($user)->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'PERSONAL', 'workspaceId' => $workspace->id, 'toolId' => $tool->id])
            ->assertStatus(503)->assertJsonPath('error.code', 'RUNTIME_ORIGIN_MISCONFIGURED');
    }

    public function test_shared_cookie_domain_cannot_expose_platform_session_to_tool_host(): void
    {
        [$user, $tool, , $workspace] = $this->personal();
        config(['tools.platform_origin' => 'https://app.example.test', 'tools.runtime_origin' => 'https://tools.example.test', 'session.domain' => '.example.test']);
        $this->actingAs($user)->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'PERSONAL', 'workspaceId' => $workspace->id, 'toolId' => $tool->id])
            ->assertStatus(503)->assertJsonPath('error.code', 'RUNTIME_ORIGIN_MISCONFIGURED');
    }

    public function test_document_crud_content_etags_and_cursor_are_workspace_bound(): void
    {
        [, , , , $grant] = $this->personal();
        $headers = $this->bearer($grant);
        $folder = $this->postJson($this->api('/documents'), ['entryKind' => 'FOLDER', 'name' => 'Folder'], $headers)->assertCreated()->json('document.id');
        $file = $this->postJson($this->api('/documents'), ['entryKind' => 'FILE', 'name' => 'hello.txt', 'parentFolderId' => $folder], $headers)->assertCreated()->json('document.id');
        $content = $this->get($this->api('/documents/'.$file.'/content'), $headers)->assertOk()->assertContent('');
        $etag = $content->headers->get('ETag');
        $this->call('PUT', $this->api('/documents/'.$file.'/content'), [], [], [], $this->server($grant), 'hello')->assertStatus(428);
        $response = $this->call('PUT', $this->api('/documents/'.$file.'/content'), [], [], [], $this->server($grant, ['HTTP_IF_MATCH' => $etag, 'CONTENT_TYPE' => 'text/plain']), 'hello')->assertOk();
        $this->assertSame('"'.hash('sha256', 'hello').'"', $response->headers->get('ETag'));
        $this->call('PUT', $this->api('/documents/'.$file.'/content'), [], [], [], $this->server($grant, ['HTTP_IF_MATCH' => $etag]), 'overwrite')->assertStatus(412)->assertJsonPath('error.code', 'PRECONDITION_FAILED');
        $this->patchJson($this->api('/documents/'.$file), ['name' => 'renamed.txt'], $headers)->assertOk()->assertJsonPath('document.name', 'renamed.txt');
        $this->getJson($this->api('/documents?parentFolderId='.$folder), $headers)->assertOk()->assertJsonCount(1, 'documents');
        $this->get($this->api('/documents/'.$file.'/content'), $headers)->assertOk()->assertContent('hello');
        $this->postJson($this->api('/documents'), ['entryKind' => 'FILE', 'name' => 'root.txt'], $headers)->assertCreated();
        $first = $this->getJson($this->api('/documents?limit=1'), $headers)->assertOk()->json('nextCursor');
        $this->assertNotNull($first);
        $this->getJson($this->api('/documents?limit=1&cursor='.$first), $headers)->assertOk()->assertJsonCount(1, 'documents')->assertJsonPath('nextCursor', null);
        $this->deleteJson($this->api('/documents/'.$folder), [], $headers)->assertNoContent();
        $this->getJson($this->api('/documents/'.$file), $headers)->assertNotFound();
    }

    public function test_documents_reject_cross_workspace_ids_and_folder_cycles(): void
    {
        [, , , , $first] = $this->personal();
        [, , , , $second] = $this->personal();
        $root = $this->postJson($this->api('/documents'), ['entryKind' => 'FOLDER', 'name' => 'First'], $this->bearer($first))->assertCreated()->json('document.id');
        $child = $this->postJson($this->api('/documents'), ['entryKind' => 'FOLDER', 'name' => 'Child', 'parentFolderId' => $root], $this->bearer($first))->assertCreated()->json('document.id');
        $this->getJson($this->api('/documents/'.$root), $this->bearer($second))->assertNotFound();
        $this->patchJson($this->api('/documents/'.$root), ['parentFolderId' => $child], $this->bearer($first))->assertUnprocessable();
        $this->postJson($this->api('/documents'), ['entryKind' => 'FILE', 'name' => '../escape'], $this->bearer($first))->assertUnprocessable();
        $this->postJson($this->api('/documents'), ['entryKind' => 'FILE', 'name' => 'Foreign', 'parentFolderId' => $root], $this->bearer($second))->assertUnprocessable();
    }

    public function test_work_launch_derives_attempt_workspace_and_pins_tool_version(): void
    {
        [$user, $tool, $version, $attempt, $assignment] = $this->workFixture();
        $grant = $this->launchWork($user, $tool, $attempt, $assignment);
        $this->getJson($this->api('/context'), $this->bearer($grant))->assertOk()->assertJsonPath('attempt.id', $attempt->id)
            ->assertJsonPath('tool.version.id', $version->id)->assertJsonPath('session.readOnly', false);
        $work = AssignmentWorkSession::findOrFail($grant['session']['id']);
        $this->assertSame($version->id, $work->tool_version_id);
        $new = $this->version($user, $tool, '2.0.0');
        $tool->update(['active_version_id' => $new->id]);
        $this->postJson($this->api('/token'), [], $this->bearer($grant))->assertOk()->assertJsonPath('session.id', $work->id);
        $this->getJson($this->api('/context'), $this->bearer($grant))->assertOk()->assertJsonPath('tool.version.id', $version->id);
    }

    public function test_work_launch_requires_own_attempt_allowed_tool_and_selected_scope(): void
    {
        [$user, $tool, , $attempt, $assignment] = $this->workFixture();
        $other = $this->user();
        $this->actingAs($other)->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'WORK', 'attemptId' => $attempt->id, 'toolId' => $tool->id])->assertNotFound();
        $this->actingAs($user)->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'WORK', 'attemptId' => $attempt->id, 'toolId' => $tool->id, 'roleAssignmentId' => Str::uuid()])->assertForbidden();
        [$foreignTool] = $this->tool($user);
        $this->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'WORK', 'attemptId' => $attempt->id, 'toolId' => $foreignTool->id, 'roleAssignmentId' => $assignment->id])->assertForbidden();
        $assignment->update(['revoked_at' => now()]);
        $this->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'WORK', 'attemptId' => $attempt->id, 'toolId' => $tool->id, 'roleAssignmentId' => $assignment->id])->assertForbidden();
        $this->assertDatabaseCount('assignment_work_sessions', 0);
    }

    public function test_events_are_contiguous_idempotent_and_server_hashed(): void
    {
        [$user, $tool, , $attempt, $assignment] = $this->workFixture();
        $grant = $this->launchWork($user, $tool, $attempt, $assignment); $headers = $this->bearer($grant);
        $this->getJson($this->api('/events/checkpoint'), $headers)->assertOk()->assertJsonPath('nextSeq', 0);
        $batch = $this->batch();
        $receipt = $this->postJson($this->api('/events'), $batch, $headers)->assertCreated()->assertJsonPath('duplicate', false);
        $this->postJson($this->api('/events'), $batch, $headers)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('batch.id', $receipt->json('batch.id'));
        $this->postJson($this->api('/events'), array_replace($batch, ['events' => [['changed' => true]]]), $headers)->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->postJson($this->api('/events'), $this->batch(['seqStart' => 2, 'seqEnd' => 2]), $headers)->assertConflict()->assertJsonPath('error.details.expectedSeqStart', 1);
        $second = $this->postJson($this->api('/events'), $this->batch(['seqStart' => 1, 'seqEnd' => 1]), $headers)->assertCreated();
        $this->assertSame($receipt->json('batch.chainHash'), $second->json('batch.previousChainHash'));
        $this->assertDatabaseCount('event_batches', 2);
        $this->getJson($this->api('/events/checkpoint'), $headers)->assertOk()->assertJsonPath('checkpoint', 1)->assertJsonPath('nextSeq', 2);
    }

    public function test_event_validation_rejects_mismatched_ranges_and_large_batches(): void
    {
        [$user, $tool, , $attempt, $assignment] = $this->workFixture();
        $grant = $this->launchWork($user, $tool, $attempt, $assignment); $headers = $this->bearer($grant);
        $this->postJson($this->api('/events'), $this->batch(['seqEnd' => 1]), $headers)->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->postJson($this->api('/events'), $this->batch(['events' => [['data' => str_repeat('a', 1048576)]]]), $headers)->assertStatus(413);
        $this->postJson($this->api('/events'), $this->batch(['clientAt' => 'not-a-date']), $headers)->assertUnprocessable();
        $this->assertDatabaseCount('event_batches', 0);
    }

    public function test_replay_preserves_empty_objects_and_returns_verifiable_hashes(): void
    {
        [$user, $tool, , $attempt, $assignment] = $this->workFixture();
        $grant = $this->launchWork($user, $tool, $attempt, $assignment);
        $events = [json_decode('{"empty":{},"array":[],"numericKeys":{"10":{},"2":[]},"small":1e-7,"big":1e20,"text":"\u6c49\u5b57/\u00e9"}')];
        $batch = $this->batch(['events' => $events]);
        $receipt = $this->postJson($this->api('/events'), $batch, $this->bearer($grant))->assertCreated();
        $response = $this->getJson($this->api('/replay/sessions/'.$grant['session']['id'].'/events'), $this->bearer($grant))->assertOk();
        $replayed = json_decode($response->getContent())->batches[0];
        $this->assertInstanceOf(\stdClass::class, $replayed->events[0]->empty);
        $payload = ['sessionId' => $grant['session']['id'], 'clientBatchId' => $replayed->clientBatchId, 'seqStart' => $replayed->seqStart, 'seqEnd' => $replayed->seqEnd, 'clientAt' => $replayed->clientAt, 'events' => $replayed->events];
        $this->assertSame($receipt->json('batch.payloadHash'), hash('sha256', ToolRuntimeService::canonicalJson($payload)));
        $this->assertSame('2026-01-01T00:00:00.123Z', $replayed->clientAt);
        $this->assertSame($replayed->chainHash, hash('sha256', ToolRuntimeService::canonicalJson(['previousChainHash' => null, 'payloadHash' => $replayed->payloadHash])));
    }

    public function test_canonical_json_matches_reference_javascript_for_unicode_and_exponents(): void
    {
        $value = json_decode('{"z":{},"a":[1e-7,1e-6,1e20,1e21,-0.0,1.234e-5,"\u6c49\u5b57/\u00e9",{"10":{},"2":[]},"\u2028",9007199254740993],"\ud83d\ude00":true,"\uffff":false}');
        // Independently calculated by Node using packages/replay-core canonicalJson's algorithm.
        $this->assertSame('7ffc61326f42fb709cb495e94fe75eea11f0038f14c73528303ecc8afd9f9956', hash('sha256', ToolRuntimeService::canonicalJson($value)));
    }

    public function test_submission_immediately_removes_write_and_recording_rights(): void
    {
        [$user, $tool, , $attempt, $assignment] = $this->workFixture();
        $grant = $this->launchWork($user, $tool, $attempt, $assignment); $headers = $this->bearer($grant);
        $this->actingAs($user)->postJson('http://localhost:8000/api/v1/me/attempts/'.$attempt->id.'/submit')->assertOk();
        $this->postJson($this->api('/documents'), ['entryKind' => 'FILE', 'name' => 'Late'], $headers)->assertForbidden();
        $this->postJson($this->api('/events'), $this->batch(), $headers)->assertForbidden();
        $this->getJson($this->api('/context'), $headers)->assertOk()->assertJsonPath('session.readOnly', true);
        $this->postJson($this->api('/token'), [], $headers)->assertOk()->assertJsonPath('session.readOnly', true);
        $this->actingAs($user)->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'WORK', 'attemptId' => $attempt->id, 'toolId' => $tool->id])->assertForbidden();
    }

    public function test_replay_launch_is_read_only_and_can_use_new_active_runtime(): void
    {
        [$user, $tool, $version, $attempt, $assignment] = $this->workFixture();
        $work = $this->launchWork($user, $tool, $attempt, $assignment);
        $new = $this->version($user, $tool, '2.0.0'); $tool->update(['active_version_id' => $new->id]);
        $reviewer = $this->user();
        $role = $this->grant($reviewer, $attempt->assessment->offering, ['attempt.view_all', 'tool_session.replay_all']);
        $replay = $this->actingAs($reviewer)->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'REPLAY', 'workSessionId' => $work['session']['id'], 'roleAssignmentId' => $role->id])->assertCreated()->json();
        $this->getJson($this->api('/context'), $this->bearer($replay))->assertOk()->assertJsonPath('session.mode', 'REVIEWER')->assertJsonPath('tool.version.id', $new->id);
        $this->getJson($this->api('/replay/sessions'), $this->bearer($replay))->assertOk()->assertJsonPath('sessions.0.toolVersion.id', $version->id)->assertJsonPath('sessions.0.currentRuntimeCompatible', false);
        $this->postJson($this->api('/documents'), ['entryKind' => 'FILE', 'name' => 'Forbidden'], $this->bearer($replay))->assertForbidden();
        $this->patchJson($this->api('/session'), [], $this->bearer($replay))->assertForbidden();
        $this->getJson($this->api('/replay/sessions/'.Str::uuid().'/events'), $this->bearer($replay))->assertNotFound();
    }

    public function test_replay_access_cannot_cross_attempt_or_selected_role_context(): void
    {
        [$user, $tool, , $attempt, $assignment] = $this->workFixture();
        $first = $this->launchWork($user, $tool, $attempt, $assignment);
        [$other, $otherTool, , $otherAttempt, $otherRole] = $this->workFixture();
        $second = $this->launchWork($other, $otherTool, $otherAttempt, $otherRole);
        $this->getJson($this->api('/replay/sessions/'.$second['session']['id'].'/events'), $this->bearer($first))->assertNotFound();
        $this->actingAs($other)->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'REPLAY', 'workSessionId' => $first['session']['id'], 'roleAssignmentId' => $otherRole->id])->assertForbidden();
        $narrow = $this->grant($user, $attempt->assessment->offering, ['attempt.view_own']);
        $this->actingAs($user)->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'REPLAY', 'workSessionId' => $first['session']['id'], 'roleAssignmentId' => $narrow->id])->assertForbidden();
    }

    public function test_role_revocation_account_deactivation_and_logout_revoke_tokens(): void
    {
        [$user, $tool, , $attempt, $assignment] = $this->workFixture();
        $grant = $this->launchWork($user, $tool, $attempt, $assignment);
        $assignment->update(['revoked_at' => now()]);
        $this->postJson($this->api('/token'), [], $this->bearer($grant))->assertForbidden();
        [, , , , $personal] = $this->personal($user);
        $user->forceFill(['is_active' => false])->save();
        // The isolated tool origin has no platform login cookie; authenticate only its JWT here.
        $this->app['auth']->forgetGuards();
        $this->getJson($this->api('/context'), $this->bearer($personal))->assertUnauthorized();
        $user->forceFill(['is_active' => true])->save();
        $this->actingAs($user)->postJson('http://localhost:8000/api/v1/auth/logout-all')->assertNoContent();
        $this->getJson($this->api('/context'), $this->bearer($personal))->assertUnauthorized();
    }

    public function test_expired_login_session_and_explicit_runtime_revocation_prevent_refresh(): void
    {
        [, , , , $grant] = $this->personal();
        $runtime = ToolRuntimeSession::findOrFail($grant['session']['runtimeSessionId']);
        $runtime->update(['login_session_id' => 'deleted-login']);
        $this->postJson($this->api('/token'), [], $this->bearer($grant))->assertUnauthorized();
        $runtime->update(['login_session_id' => null, 'revoked_at' => now()]);
        $this->getJson($this->api('/context'), $this->bearer($grant))->assertUnauthorized();
    }

    public function test_expired_wrong_audience_and_tampered_jwts_are_rejected(): void
    {
        [, , , , $grant] = $this->personal();
        $claims = (array) json_decode(base64_decode(strtr(explode('.', $grant['token'])[1], '-_', '+/')));
        $expired = JWT::encode(array_replace($claims, ['iat' => time() - 1000, 'exp' => time() - 1]), config('tools.token_secret'), 'HS256');
        $wrongAudience = JWT::encode(array_replace($claims, ['aud' => 'other-service']), config('tools.token_secret'), 'HS256');
        $tampered = JWT::encode($claims, str_repeat('x', 64), 'HS256');
        foreach ([$expired, $wrongAudience, $tampered] as $token) $this->getJson($this->api('/context'), ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    public function test_runtime_cookie_is_host_only_path_scoped_and_assets_are_protected(): void
    {
        [, $tool, $version, , $grant] = $this->personal();
        $path = '/tools/'.$tool->code.'/'.$version->id.'/index.html';
        $this->get('http://127.0.0.1:8000'.$path)->assertUnauthorized();
        $response = $this->postJson($this->api('/runtime-auth'), [], $this->bearer($grant))->assertOk()->assertJsonPath('runtimePath', $path);
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === ToolRuntimeService::COOKIE);
        $this->assertNotNull($cookie); $this->assertNull($cookie->getDomain()); $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/tools/'.$tool->code.'/'.$version->id, $cookie->getPath());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->withUnencryptedCookie(ToolRuntimeService::COOKIE, $grant['token'])->get('http://127.0.0.1:8000'.$path)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $this->get('http://localhost:8000'.$path)->assertForbidden();
        $this->get('http://127.0.0.1:8000/tools/'.$tool->code.'/'.Str::uuid().'/index.html')->assertNotFound();
        $this->getJson('http://127.0.0.1:8000/api/v1/institutions')->assertNotFound();
        $this->get('http://127.0.0.1:8000/tool-runtime/bootstrap')->assertOk()->assertSee('history.replaceState', false);
        $refresh = $this->postJson($this->api('/token'), [], $this->bearer($grant))->assertOk();
        $this->assertTrue(collect($refresh->headers->getCookies())->contains(fn ($cookie) => $cookie->getName() === ToolRuntimeService::COOKIE));
    }

    public function test_closing_work_session_is_idempotent_and_stops_further_access(): void
    {
        [$user, $tool, , $attempt, $assignment] = $this->workFixture();
        $grant = $this->launchWork($user, $tool, $attempt, $assignment); $headers = $this->bearer($grant);
        $first = $this->patchJson($this->api('/session'), [], $headers)->assertOk()->json('session.endAt');
        $this->patchJson($this->api('/session'), [], $headers)->assertOk()->assertJsonPath('session.endAt', $first);
        $this->assertNotNull(AssignmentWorkSession::findOrFail($grant['session']['id'])->end_at);
        $this->getJson($this->api('/context'), $headers)->assertUnauthorized();
    }

    private function api(string $path): string { return 'http://127.0.0.1:8000/api/tool/v1'.$path; }
    private function bearer(array $grant): array { return ['Authorization' => 'Bearer '.$grant['token']]; }
    private function server(array $grant, array $extra = []): array { return $extra + ['HTTP_AUTHORIZATION' => 'Bearer '.$grant['token'], 'HTTP_ACCEPT' => 'application/json']; }
    private function batch(array $overrides = []): array { return array_replace(['batchId' => (string) Str::uuid(), 'seqStart' => 0, 'seqEnd' => 0, 'clientAt' => '2026-01-01T00:00:00.123Z', 'events' => [['type' => 'opened']]], $overrides); }
    private function user(): User { return User::forceCreate(['name' => 'Runtime user', 'email' => Str::uuid().'@example.test', 'password' => 'runtime-password', 'is_active' => true])->refresh(); }

    private function personal(?User $user = null): array
    {
        $user ??= $this->user(); [$tool, $version] = $this->tool($user);
        $workspace = app(WorkspaceService::class)->createPersonal($user, 'Personal workspace');
        $grant = $this->actingAs($user)->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'PERSONAL', 'workspaceId' => $workspace->id, 'toolId' => $tool->id])->assertCreated()->json();
        return [$user, $tool, $version, $workspace, $grant];
    }

    private function tool(User $user): array
    {
        $tool = Tool::create(['code' => 'tool-'.Str::lower(Str::random(8)), 'name' => 'Test tool']);
        $version = $this->version($user, $tool, '1.0.0');
        $tool->update(['active_version_id' => $version->id]);
        return [$tool, $version];
    }

    private function version(User $user, Tool $tool, string $number): ToolVersion
    {
        $prefix = 'tool-assets/'.$tool->id.'/'.Str::uuid();
        Storage::disk('local')->put($prefix.'/index.html', '<!doctype html><title>Test tool</title>');
        return ToolVersion::create(['tool_id' => $tool->id, 'created_by_id' => $user->id, 'version' => $number, 'entry_path' => 'index.html',
            'manifest' => ['schemaVersion' => 1, 'code' => $tool->code, 'version' => $number, 'entry' => 'index.html'],
            'archive_key' => 'archives/'.Str::uuid().'.zip', 'storage_prefix' => $prefix, 'sha256' => str_repeat('a', 64), 'compressed_size' => 100, 'unpacked_size' => 100, 'file_count' => 1]);
    }

    private function workFixture(): array
    {
        $user = $this->user(); [$tool, $version] = $this->tool($user);
        $institution = Institution::create(['name' => 'Runtime university']);
        $subject = $institution->subjects()->create(['subject_code' => 'COMP1000', 'subject_name' => 'Programming']);
        $term = $institution->terms()->create(['year' => 2026, 'session' => 1]);
        $offering = $institution->offerings()->create(['subject_id' => $subject->id, 'term_id' => $term->id]);
        $assessment = Assessment::create(['offering_id' => $offering->id, 'name' => 'Runtime assessment', 'weight' => 100, 'publish_at' => now()->subDay()]);
        $assessment->allowedTools()->sync([$tool->id]);
        $attempt = AssessmentAttempt::create(['user_id' => $user->id, 'assessment_id' => $assessment->id, 'start_at' => now()]);
        $assignment = $this->grant($user, $offering, ['offering.view', 'assessment.view_published', 'assessment.attempt', 'attempt.view_own', 'tool.launch', 'tool_session.replay_own']);
        return [$user, $tool, $version, $attempt, $assignment];
    }

    private function grant(User $user, Offering $offering, array $permissions): RoleAssignment
    {
        $role = Role::create(['institution_id' => $offering->institution_id, 'role_code' => 'R-'.Str::uuid(), 'role_name' => 'Runtime role', 'scope_type' => 'OFFERING']);
        $role->permissions()->sync($permissions);
        $assignment = RoleAssignment::create(['user_id' => $user->id, 'role_id' => $role->id]);
        ScopeAssignment::create(['role_assignment_id' => $assignment->id, 'offering_id' => $offering->id]);
        return $assignment;
    }

    private function launchWork(User $user, Tool $tool, AssessmentAttempt $attempt, RoleAssignment $assignment): array
    {
        return $this->actingAs($user)->postJson('http://localhost:8000/api/tool/v1/sessions', ['mode' => 'WORK', 'attemptId' => $attempt->id, 'toolId' => $tool->id, 'roleAssignmentId' => $assignment->id])->assertCreated()->json();
    }
}
