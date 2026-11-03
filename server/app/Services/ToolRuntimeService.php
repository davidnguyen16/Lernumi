<?php

namespace App\Services;

use App\Exceptions\ToolApiException;
use App\Models\AssessmentAttempt;
use App\Models\AssignmentWorkSession;
use App\Models\EventBatch;
use App\Models\RoleAssignment;
use App\Models\Tool;
use App\Models\ToolRuntimeSession;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/** Server-owned capability sessions. Every request rechecks revocation and resource boundaries. */
class ToolRuntimeService
{
    public const COOKIE = 'lernumi_tool_runtime';

    public function __construct(private readonly AccessService $access, private readonly WorkspaceService $workspaces) {}

    public function launch(Request $request, array $data): array
    {
        $this->origins();
        $user = $request->user();
        $this->require($user?->is_active, 'AUTHENTICATION_REQUIRED', 'Your account is not active.', 401);
        $assignmentId = $data['roleAssignmentId'] ?? null;
        $this->assignment($user, $assignmentId);
        return DB::transaction(function () use ($request, $data, $user, $assignmentId): array {
            $workSession = null;
            if ($data['mode'] === 'PERSONAL') {
                $workspace = Workspace::query()->with('personal')->find($data['workspaceId']);
                $this->require($workspace?->personal?->user_id === $user->id, 'NOT_FOUND', 'Personal workspace not found.', 404);
                $tool = Tool::query()->with('activeVersion')->find($data['toolId']);
                $mode = 'OWNER';
            } elseif ($data['mode'] === 'WORK') {
                $attempt = AssessmentAttempt::query()->with('assessment.offering')->lockForUpdate()->find($data['attemptId']);
                $this->require($attempt && $attempt->isOwnedBy($user), 'NOT_FOUND', 'Attempt not found.', 404);
                $this->require($attempt->submit_at === null, 'WORKSPACE_READ_ONLY', 'This attempt has already been submitted.', 403);
                $this->require($attempt->assessment->publish_at && $attempt->assessment->publish_at->lessThanOrEqualTo(now()), 'NOT_FOUND', 'Assessment not found.', 404);
                $offering = $attempt->assessment->offering;
                foreach (['tool.launch', 'attempt.view_own'] as $permission) $this->permission($user, $permission, $offering, $assignmentId);
                $tool = Tool::query()->with('activeVersion')->find($data['toolId']);
                $this->require($tool && $attempt->assessment->allowedTools()->whereKey($tool->id)->exists(), 'AUTHORIZATION_FORBIDDEN', 'This tool is not allowed by the assessment.', 403);
                $workspace = $this->workspaces->forAttempt($attempt);
                $mode = 'OWNER';
                $this->require($tool->activeVersion && $tool->activeVersion->tool_id === $tool->id, 'RESOURCE_IN_USE', 'This tool has no active version.', 409);
                $workSession = AssignmentWorkSession::query()->create(['assignment_workspace_id' => $workspace->id,
                    'tool_id' => $tool->id, 'tool_version_id' => $tool->activeVersion->id, 'user_id' => $user->id, 'start_at' => now()]);
            } else {
                $workSession = AssignmentWorkSession::query()->with('workspace.attempt.assessment.offering', 'tool.activeVersion')->find($data['workSessionId']);
                $this->require($workSession !== null, 'NOT_FOUND', 'Recorded session not found.', 404);
                $workspace = $workSession->workspace->workspace;
                $tool = $workSession->tool;
                $mode = 'REVIEWER';
            }
            $this->require($tool?->activeVersion && $tool->activeVersion->tool_id === $tool->id, 'RESOURCE_IN_USE', 'This tool has no active version.', 409);
            $loginId = $request->hasSession() && ! ($user->currentAccessToken() instanceof PersonalAccessToken) ? $request->session()->getId() : null;
            $runtime = ToolRuntimeSession::query()->create(['user_id' => $user->id, 'login_session_id' => $loginId,
                'role_assignment_id' => $assignmentId, 'workspace_id' => $workspace->id, 'tool_id' => $tool->id,
                'tool_version_id' => $tool->activeVersion->id, 'work_session_id' => $workSession?->id,
                'mode' => $mode, 'read_only' => $mode === 'REVIEWER', 'capabilities' => [], 'expires_at' => now()->addSeconds($this->ttl())]);
            $capabilities = $this->authorizeRuntime($runtime, false);
            $runtime->update(['capabilities' => $capabilities, 'read_only' => ! in_array('files:write', $capabilities, true)]);
            $workspace->update(['last_opened_at' => now()]);
            return $this->grant($runtime);
        });
    }

    public function resolve(Request $request, ?string $capability = null, bool $allowCookie = false, bool $allowClosed = false): ToolRuntimeSession
    {
        $token = $request->bearerToken() ?: ($allowCookie ? $request->cookie(self::COOKIE) : null);
        $this->require(is_string($token) && $token !== '', 'AUTHENTICATION_REQUIRED', 'A tool bearer token is required.', 401);
        $secret = $this->secret();
        try { $claims = JWT::decode($token, new Key($secret, 'HS256')); }
        catch (\Throwable $exception) { throw new ToolApiException('AUTHENTICATION_REQUIRED', 'The tool token is invalid or expired.', 401); }
        $this->require(($claims->iss ?? null) === 'lernumi' && ($claims->aud ?? null) === 'lernumi-tool-api'
            && is_int($claims->exp ?? null) && is_int($claims->iat ?? null)
            && is_string($claims->jti ?? null) && Str::isUuid($claims->jti)
            && is_string($claims->runtimeSessionId ?? null) && Str::isUuid($claims->runtimeSessionId), 'AUTHENTICATION_REQUIRED', 'The tool token is invalid.', 401);
        $runtime = ToolRuntimeSession::query()->find($claims->runtimeSessionId);
        $this->require($runtime && $runtime->user_id === ($claims->sub ?? null) && $runtime->workspace_id === ($claims->assignmentWorkspaceId ?? null)
            && $runtime->tool_id === ($claims->toolId ?? null) && $runtime->tool_version_id === ($claims->toolVersionId ?? null), 'AUTHENTICATION_REQUIRED', 'The runtime session is unavailable.', 401);
        $capabilities = $this->authorizeRuntime($runtime, true, $allowClosed);
        $runtime->setAttribute('capabilities', array_values(array_intersect($capabilities, (array) ($claims->capabilities ?? []))));
        $runtime->setAttribute('read_only', ! in_array('files:write', $runtime->capabilities, true));
        if ($capability) $this->require(in_array($capability, $runtime->capabilities, true), 'AUTHORIZATION_FORBIDDEN', 'This runtime does not grant '.$capability.'.', 403);
        $request->attributes->set('tool_runtime', $runtime);
        return $runtime;
    }

    public function refresh(ToolRuntimeSession $runtime): array
    {
        $runtime->update(['capabilities' => $this->authorizeRuntime($runtime), 'expires_at' => now()->addSeconds($this->ttl())]);
        return $this->grant($runtime);
    }

    private function authorizeRuntime(ToolRuntimeSession $runtime, bool $checkLogin = true, bool $allowClosed = false): array
    {
        $runtime->loadMissing('user', 'workspace.personal', 'workspace.assignment.attempt.assessment.offering', 'toolVersion', 'workSession');
        $user = $runtime->user;
        $this->require($user?->is_active && ($allowClosed || ! $runtime->revoked_at) && $runtime->expires_at->isFuture(), 'AUTHENTICATION_REQUIRED', 'The runtime session has expired or been revoked.', 401);
        $this->require($runtime->toolVersion?->tool_id === $runtime->tool_id, 'AUTHENTICATION_REQUIRED', 'The pinned tool version is unavailable.', 401);
        if ($checkLogin && $runtime->login_session_id) {
            $validLogin = DB::table('sessions')->where('id', $runtime->login_session_id)->where('user_id', $user->id)
                ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->timestamp)->exists();
            $this->require($validLogin, 'AUTHENTICATION_REQUIRED', 'The platform login session is no longer active.', 401);
        }
        $this->assignment($user, $runtime->role_assignment_id);
        if ($runtime->workspace->personal) {
            $this->require($runtime->workspace->personal->user_id === $user->id && $runtime->mode === 'OWNER' && $runtime->work_session_id === null, 'AUTHORIZATION_FORBIDDEN', 'The personal workspace is unavailable.', 403);
            return ['files:read', 'files:write'];
        }
        $attempt = $runtime->workspace->assignment?->attempt;
        $work = $runtime->workSession;
        $this->require($attempt && $work && $work->assignment_workspace_id === $runtime->workspace_id && $work->tool_id === $runtime->tool_id, 'NOT_FOUND', 'The recorded workspace is unavailable.', 404);
        $offering = $attempt->assessment->offering;
        $own = $attempt->isOwnedBy($user);
        if ($runtime->mode === 'REVIEWER') {
            $this->permission($user, $own ? 'attempt.view_own' : 'attempt.view_all', $offering, $runtime->role_assignment_id);
            $this->permission($user, $own ? 'tool_session.replay_own' : 'tool_session.replay_all', $offering, $runtime->role_assignment_id);
            return ['files:read', 'replay:read'];
        }
        $this->require($own && $work->user_id === $user->id && $work->tool_version_id === $runtime->tool_version_id, 'AUTHORIZATION_FORBIDDEN', 'This work session belongs to another member.', 403);
        $this->permission($user, 'tool.launch', $offering, $runtime->role_assignment_id);
        $this->permission($user, 'attempt.view_own', $offering, $runtime->role_assignment_id);
        $this->require($attempt->assessment->allowedTools()->whereKey($runtime->tool_id)->exists(), 'AUTHORIZATION_FORBIDDEN', 'This tool is no longer allowed by the assessment.', 403);
        $capabilities = ['files:read'];
        if ($attempt->submit_at === null && $work->end_at === null) $capabilities = array_merge($capabilities, ['files:write', 'events:append']);
        if ($this->allowed($user, 'tool_session.replay_own', $offering, $runtime->role_assignment_id)) $capabilities[] = 'replay:read';
        return $capabilities;
    }

    public function close(ToolRuntimeSession $runtime): array
    {
        $this->require($runtime->mode === 'OWNER', 'AUTHORIZATION_FORBIDDEN', 'Replay sessions cannot close recorded work.', 403);
        return DB::transaction(function () use ($runtime): array {
            $work = $runtime->workSession;
            if ($work && $runtime->mode === 'OWNER') {
                AssessmentAttempt::query()->whereKey($runtime->workspace->assignment->attempt_id)->lockForUpdate()->firstOrFail();
                $work = AssignmentWorkSession::query()->lockForUpdate()->findOrFail($work->id);
                if (! $work->end_at) { $work->update(['end_at' => now()]); $work->refresh(); }
            }
            if (! $runtime->revoked_at) { $runtime->update(['revoked_at' => now()]); $runtime->refresh(); }
            return ['session' => ['id' => $work?->id ?? $runtime->id, 'endAt' => ($work?->end_at ?? $runtime->revoked_at)->toISOString()]];
        });
    }

    public function append(ToolRuntimeSession $runtime, array $data): array
    {
        return DB::transaction(function () use ($runtime, $data): array {
            $attempt = AssessmentAttempt::query()->whereKey($runtime->workspace->assignment->attempt_id)->lockForUpdate()->firstOrFail();
            $session = AssignmentWorkSession::query()->lockForUpdate()->findOrFail($runtime->work_session_id);
            $this->require($attempt->submit_at === null && $session->end_at === null, 'WORKSPACE_READ_ONLY', 'The work session is closed or submitted.', 403);
            $payload = ['sessionId' => $session->id, 'clientBatchId' => $data['batchId'], 'seqStart' => (int) $data['seqStart'],
                'seqEnd' => (int) $data['seqEnd'], 'clientAt' => CarbonImmutable::parse($data['clientAt'])->utc()->format('Y-m-d\TH:i:s.v\Z'), 'events' => $data['events']];
            $payloadHash = hash('sha256', self::canonicalJson($payload));
            $duplicate = $session->eventBatches()->where('client_batch_id', $data['batchId'])->first();
            if ($duplicate) {
                $this->require(hash_equals($duplicate->payload_hash, $payloadHash), 'IDEMPOTENCY_CONFLICT', 'This batchId was already used for different events.', 409);
                return ['created' => false, 'body' => $this->receipt($duplicate, true)];
            }
            $previous = $session->eventBatches()->orderByDesc('seq_end')->first();
            $expected = ($previous?->seq_end ?? -1) + 1;
            if ($payload['seqStart'] !== $expected) throw new ToolApiException('EVENT_SEQUENCE_CONFLICT', 'The event sequence does not continue from the server checkpoint.', 409, ['expectedSeqStart' => $expected]);
            $previousHash = $previous?->chain_hash;
            $batch = $session->eventBatches()->create(['client_batch_id' => $data['batchId'], 'seq_start' => $payload['seqStart'], 'seq_end' => $payload['seqEnd'],
                'client_at' => $payload['clientAt'], 'received_at' => now(), 'events' => $data['events'], 'payload_hash' => $payloadHash,
                'previous_chain_hash' => $previousHash, 'chain_hash' => hash('sha256', self::canonicalJson(['previousChainHash' => $previousHash, 'payloadHash' => $payloadHash]))]);
            return ['created' => true, 'body' => $this->receipt($batch, false)];
        });
    }

    private function receipt(EventBatch $batch, bool $duplicate): array
    {
        return ['accepted' => true, 'duplicate' => $duplicate, 'checkpoint' => $batch->seq_end,
            'batch' => ['id' => $batch->id, 'seqStart' => $batch->seq_start, 'seqEnd' => $batch->seq_end, 'receivedAt' => $batch->received_at->toISOString(),
                'payloadHash' => $batch->payload_hash, 'previousChainHash' => $batch->previous_chain_hash, 'chainHash' => $batch->chain_hash]];
    }

    private function assignment(User $user, ?string $assignmentId): void
    {
        if ($assignmentId === null) return;
        $this->require(RoleAssignment::query()->whereKey($assignmentId)->where('user_id', $user->id)->whereNull('revoked_at')->exists(), 'AUTHORIZATION_FORBIDDEN', 'The selected role assignment is not active for this user.', 403);
    }

    private function permission(User $user, string $permission, $offering, ?string $assignmentId): void
    {
        $this->require($this->allowed($user, $permission, $offering, $assignmentId), 'AUTHORIZATION_FORBIDDEN', 'The selected role does not grant '.$permission.'.', 403);
    }

    private function allowed(User $user, string $permission, $offering, ?string $assignmentId): bool
    {
        return $assignmentId ? $this->access->canInContext($user, $permission, $offering->institution_id, $offering->id, $assignmentId)
            : $this->access->can($user, $permission, $offering);
    }

    public function grant(ToolRuntimeSession $runtime): array
    {
        $runtime->loadMissing('tool', 'toolVersion', 'workspace.personal', 'workSession');
        $now = now()->timestamp;
        $claims = ['iss' => 'lernumi', 'aud' => 'lernumi-tool-api', 'iat' => $now, 'exp' => $now + $this->ttl(), 'sub' => $runtime->user_id,
            'jti' => (string) Str::uuid(), 'runtimeSessionId' => $runtime->id, 'toolId' => $runtime->tool_id, 'toolVersionId' => $runtime->tool_version_id,
            'workSessionId' => $runtime->work_session_id ?? $runtime->id, 'assignmentWorkspaceId' => $runtime->workspace_id,
            'roleAssignmentId' => $runtime->role_assignment_id, 'workspaceKind' => $runtime->workspace->personal ? 'PERSONAL' : 'ASSIGNMENT',
            'mode' => $runtime->mode, 'readOnly' => $runtime->read_only, 'capabilities' => $runtime->capabilities];
        $token = JWT::encode($claims, $this->secret(), 'HS256');
        $origin = $this->origins();
        return ['token' => $token, 'tokenType' => 'Bearer', 'expiresIn' => $this->ttl(),
            'runtimeUrl' => $origin.'/tool-runtime/bootstrap#'.http_build_query(['lernumi_token' => $token, 'lernumi_api' => 'v1'], '', '&', PHP_QUERY_RFC3986),
            'session' => ['id' => $runtime->work_session_id ?? $runtime->id, 'runtimeSessionId' => $runtime->id, 'mode' => $runtime->mode, 'readOnly' => $runtime->read_only, 'capabilities' => $runtime->capabilities]];
    }

    public function origins(): string
    {
        $runtime = rtrim((string) config('tools.runtime_origin'), '/');
        $platform = rtrim((string) config('tools.platform_origin'), '/');
        $runtimeHost = strtolower((string) parse_url($runtime, PHP_URL_HOST));
        $cookieDomain = ltrim(strtolower((string) config('session.domain')), '.');
        $runtimeReceivesPlatformCookie = $cookieDomain !== '' && ($runtimeHost === $cookieDomain || str_ends_with($runtimeHost, '.'.$cookieDomain));
        $valid = filter_var($runtime, FILTER_VALIDATE_URL) && filter_var($platform, FILTER_VALIDATE_URL)
            && in_array(parse_url($runtime, PHP_URL_SCHEME), ['http', 'https'], true)
            && in_array(parse_url($platform, PHP_URL_SCHEME), ['http', 'https'], true)
            && strtolower((string) parse_url($runtime, PHP_URL_HOST)) !== strtolower((string) parse_url($platform, PHP_URL_HOST))
            && ! parse_url($runtime, PHP_URL_USER) && ! parse_url($runtime, PHP_URL_QUERY) && ! parse_url($runtime, PHP_URL_FRAGMENT)
            && ! parse_url($runtime, PHP_URL_PATH) && ! $runtimeReceivesPlatformCookie;
        $this->require($valid, 'RUNTIME_ORIGIN_MISCONFIGURED', 'Configure a separate hostname for the tool runtime.', 503);
        return $runtime;
    }

    private function ttl(): int { return max(30, min(3600, (int) config('tools.token_ttl', 300))); }

    private function secret(): string
    {
        $secret = (string) config('tools.token_secret');
        if (str_starts_with($secret, 'base64:')) $secret = base64_decode(substr($secret, 7), true) ?: '';
        $this->require(strlen($secret) >= 32, 'RUNTIME_SECRET_MISCONFIGURED', 'The tool signing secret must contain at least 32 bytes.', 503);
        return $secret;
    }

    private function require(mixed $condition, string $code, string $message, int $status): void
    {
        if (! $condition) throw new ToolApiException($code, $message, $status);
    }

    /** Match the replay-core canonical JSON format, including empty objects and UTF-16 key order. */
    public static function canonicalJson(mixed $value): string
    {
        // Browser JSON.parse represents all numbers as IEEE-754 doubles.
        if (is_int($value) && ($value > 9007199254740991 || $value < -9007199254740991)) $value = (float) $value;
        if (is_array($value) && array_is_list($value)) return '['.implode(',', array_map(self::canonicalJson(...), $value)).']';
        if (is_array($value) || is_object($value)) {
            $properties = (array) $value;
            uksort($properties, fn ($a, $b) => strcmp(mb_convert_encoding((string) $a, 'UTF-16BE', 'UTF-8'), mb_convert_encoding((string) $b, 'UTF-16BE', 'UTF-8')));
            $pairs = [];
            foreach ($properties as $key => $item) $pairs[] = self::canonicalJson((string) $key).':'.self::canonicalJson($item);
            return '{'.implode(',', $pairs).'}';
        }
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
        if (is_float($value)) {
            if ($value == 0) return '0';
            if (preg_match('/^(-?)(\d+)(?:\.(\d+))?e([+-]?\d+)$/', $encoded, $matches)) {
                $sign = $matches[1]; $whole = $matches[2]; $fraction = rtrim($matches[3] ?? '', '0'); $exponent = (int) $matches[4];
                if ($exponent >= -6 && $exponent < 21) {
                    $digits = $whole.$fraction; $point = strlen($whole) + $exponent;
                    return $sign.($point <= 0 ? '0.'.str_repeat('0', -$point).$digits : ($point >= strlen($digits) ? $digits.str_repeat('0', $point - strlen($digits)) : substr($digits, 0, $point).'.'.substr($digits, $point)));
                }
                return $sign.$whole.($fraction !== '' ? '.'.$fraction : '').'e'.($exponent >= 0 ? '+' : '').$exponent;
            }
        }
        return $encoded;
    }
}
