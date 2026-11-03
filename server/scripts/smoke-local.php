#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Models\Workspace;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Exercise the running local HTTP server, including browser cookies and CSRF.
 * Run: docker compose exec -T api php scripts/smoke-local.php
 * Optional: SMOKE_BASE_URL=http://localhost:8000; DEMO_PASSWORD matches the seeded accounts.
 * No PHPUnit database, curl extension, browser, or host-side language runtime is required.
 */
final class SmokeFailure extends RuntimeException {}

final class SmokeResponse
{
    public function __construct(public int $status, public array $headers, public string $body) {}

    public function expect(int $status, string $step): self
    {
        if ($this->status !== $status) throw new SmokeFailure($step.': expected HTTP '.$status.', received '.$this->status.'.');
        return $this;
    }

    public function json(string $step): array
    {
        try { $data = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new SmokeFailure($step.': response was not valid JSON.'); }
        if (! is_array($data)) throw new SmokeFailure($step.': response JSON had an unexpected shape.');
        return $data;
    }

    public function header(string $name): ?string { return $this->headers[strtolower($name)][0] ?? null; }
}

final class SmokeBrowser
{
    private array $cookies = [];
    public bool $loggedIn = false;
    public ?string $userId = null;

    public function __construct(private string $baseUrl, private string $origin = 'http://localhost:3000') {}

    public function json(string $method, string $path, ?array $data = null, bool $csrf = true): SmokeResponse
    {
        return $this->request($method, $path, $data === null ? null : json_encode($data, JSON_THROW_ON_ERROR),
            $data === null ? [] : ['Content-Type' => 'application/json'], $csrf);
    }

    public function request(string $method, string $path, ?string $body = null, array $extraHeaders = [], bool $csrf = true): SmokeResponse
    {
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\r\n]/', $path))
            throw new SmokeFailure('Unsafe smoke request path.');
        $headers = ['Accept' => 'application/json', 'Origin' => $this->origin, 'Referer' => $this->origin.'/'] + $extraHeaders;
        $cookieHeader = [];
        foreach ($this->cookies as $cookie) {
            if ((! $cookie['secure'] || str_starts_with($this->baseUrl, 'https://')) && str_starts_with($path, $cookie['path']))
                $cookieHeader[] = $cookie['name'].'='.$cookie['value'];
        }
        if ($cookieHeader) $headers['Cookie'] = implode('; ', $cookieHeader);
        if ($csrf && ! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            foreach ($this->cookies as $cookie) if ($cookie['name'] === 'XSRF-TOKEN') $headers['X-XSRF-TOKEN'] = rawurldecode($cookie['value']);
        }
        if ($body !== null) $headers['Content-Length'] = (string) strlen($body);
        $headerLines = [];
        foreach ($headers as $name => $value) $headerLines[] = $name.': '.$value;
        $context = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headerLines), 'content' => $body ?? '',
            'ignore_errors' => true, 'follow_location' => 0, 'max_redirects' => 0, 'timeout' => 20,
            'protocol_version' => 1.1,
        ]]);
        $http_response_header = [];
        // A connection error must not dump response data or sensitive request headers.
        $bytes = @file_get_contents($this->baseUrl.$path, false, $context);
        if ($bytes === false && $http_response_header === []) throw new SmokeFailure('Local API connection failed; start Docker and the API first.');
        $status = 0; $responseHeaders = [];
        foreach ($http_response_header as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $match)) { $status = (int) $match[1]; $responseHeaders = []; continue; }
            if (! str_contains($line, ':')) continue;
            [$name, $value] = explode(':', $line, 2);
            $responseHeaders[strtolower(trim($name))][] = trim($value);
        }
        foreach ($responseHeaders['set-cookie'] ?? [] as $cookie) $this->rememberCookie($cookie);
        return new SmokeResponse($status, $responseHeaders, $bytes === false ? '' : $bytes);
    }

    public function login(string $account, string $password): array
    {
        $this->request('GET', '/sanctum/csrf-cookie')->expect(204, 'CSRF cookie');
        for ($retry = 0; ; $retry++) {
            $response = $this->json('POST', '/api/v1/auth/login', ['email' => $account.'@lernumi.test', 'password' => $password]);
            if ($response->status !== 429 || $retry >= 1) break;
            $delay = $response->header('Retry-After');
            if (! is_string($delay) || ! ctype_digit($delay) || (int) $delay > 60) break;
            echo "Waiting for the local login rate limit...\n";
            // Keep individual sleeps short; never bypass or clear the application's rate limiter.
            for ($seconds = max(1, (int) $delay) + 1; $seconds > 0; $seconds--) sleep(1);
        }
        $data = $response->expect(200, ucfirst($account).' login')->json('Login');
        $this->loggedIn = true;
        $id = $data['data']['user']['id'] ?? null;
        if (! is_string($id) || ! Str::isUuid($id)) throw new SmokeFailure('Login did not return a user UUID.');
        $this->userId = $id;
        return $data['data'];
    }

    public function logout(): void
    {
        if (! $this->loggedIn) return;
        $this->json('POST', '/api/v1/auth/logout')->expect(204, 'Logout');
        $this->loggedIn = false;
        $this->request('GET', '/api/v1/auth/session')->expect(401, 'Session after logout');
    }

    private function rememberCookie(string $line): void
    {
        $parts = array_map('trim', explode(';', $line));
        [$name, $value] = array_pad(explode('=', array_shift($parts), 2), 2, '');
        $attributes = [];
        foreach ($parts as $part) {
            [$key, $attribute] = array_pad(explode('=', $part, 2), 2, true);
            $attributes[strtolower($key)] = $attribute;
        }
        $host = strtolower((string) parse_url($this->baseUrl, PHP_URL_HOST));
        $domain = ltrim(strtolower((string) ($attributes['domain'] ?? $host)), '.');
        if ($domain !== $host && ! str_ends_with($host, '.'.$domain)) return;
        $path = (string) ($attributes['path'] ?? '/');
        $key = $name.'|'.$domain.'|'.$path;
        $expired = isset($attributes['max-age']) && (int) $attributes['max-age'] <= 0;
        if (isset($attributes['expires'])) $expired = $expired || (strtotime((string) $attributes['expires']) ?: 0) < time();
        if ($expired) { unset($this->cookies[$key]); return; }
        $this->cookies[$key] = ['name' => $name, 'value' => $value, 'path' => $path, 'secure' => isset($attributes['secure'])];
    }
}

function smokeAssert(bool $condition, string $message): void
{
    if (! $condition) throw new SmokeFailure($message);
}

function smokeLoopbackOrigin(string $origin): string
{
    $origin = rtrim($origin, '/');
    $url = parse_url($origin);
    smokeAssert(is_array($url) && in_array($url['scheme'] ?? '', ['http', 'https'], true)
        && in_array(strtolower($url['host'] ?? ''), ['localhost', '127.0.0.1', '[::1]'], true)
        && ! isset($url['user']) && ! isset($url['pass']) && ! isset($url['query']) && ! isset($url['fragment']) && empty($url['path']),
        'The smoke destination must be a loopback HTTP origin with no credentials or path.');
    if (strtolower($url['host']) === 'localhost') {
        $addresses = gethostbynamel('localhost');
        smokeAssert($addresses !== false && $addresses !== [] && count(array_filter($addresses, fn ($address) => str_starts_with($address, '127.'))) === count($addresses), 'localhost must resolve only to loopback addresses.');
    }
    return $origin;
}

/** Remove only this run's known personal workspace and content hashes, including on failure. */
function cleanupSmokeWorkspace(?string &$workspaceId, string $workspaceName, ?SmokeBrowser $student, array $contentHashes): void
{
    if (! $student?->userId) return;
    $owned = Workspace::query()->where('name', $workspaceName)
        ->whereHas('personal', fn ($query) => $query->where('user_id', $student->userId))->get();
    smokeAssert($owned->count() <= 1, 'Cleanup found an ambiguous temporary workspace; no data was removed.');
    if ($owned->isNotEmpty()) {
        $workspace = $owned->first();
        smokeAssert($workspaceId === null || $workspaceId === $workspace->id, 'Cleanup workspace ownership mismatch.');
        $workspaceId = $workspace->id;
        try { $student->request('DELETE', '/api/v1/workspaces/'.$workspaceId)->expect(204, 'Temporary workspace cleanup'); }
        catch (Throwable) {
            // A broken HTTP session must not leave the uniquely named workspace behind.
            DB::transaction(function () use ($workspaceId, $workspaceName, $student): void {
                $workspace = Workspace::query()->whereKey($workspaceId)->where('name', $workspaceName)
                    ->whereHas('personal', fn ($query) => $query->where('user_id', $student->userId))->lockForUpdate()->first();
                smokeAssert($workspace !== null && ! $workspace->assignment()->exists(), 'Cleanup could not verify personal workspace ownership.');
                $workspace->delete();
            });
        }
    }
    if ($workspaceId === null) return;
    smokeAssert(Str::isUuid($workspaceId) && ! Workspace::query()->whereKey($workspaceId)->exists(), 'Temporary workspace still exists; content cleanup stopped.');
    $disk = Storage::disk('local');
    $prefix = 'documents/'.$workspaceId;
    $root = realpath($disk->path(''));
    $target = realpath($disk->path($prefix));
    if ($target === false) return;
    smokeAssert($root !== false && str_starts_with($target.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR.'documents'.DIRECTORY_SEPARATOR)
        && basename($target) === $workspaceId, 'Storage cleanup path verification failed.');
    foreach ($disk->allFiles($prefix) as $key) {
        $parts = explode('/', $key);
        // Never recursively delete a directory or remove a blob with an unknown content hash.
        if (count($parts) === 4 && $parts[0] === 'documents' && $parts[1] === $workspaceId
            && Str::isUuid($parts[2]) && in_array($parts[3], $contentHashes, true)) {
            smokeAssert($disk->delete($key), 'Temporary content cleanup failed.');
        }
    }
}

$browsers = []; $student = null; $workspaceId = null; $workspaceName = ''; $hashes = []; $failure = null; $booted = false;
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    smokeAssert($app->environment('local') && (getenv('APP_ENV') === false || getenv('APP_ENV') === 'local'), 'This HTTP smoke test requires APP_ENV=local.');
    smokeAssert((bool) ini_get('allow_url_fopen'), 'PHP allow_url_fopen is required for the local HTTP smoke test.');
    $baseUrl = smokeLoopbackOrigin((string) env('SMOKE_BASE_URL', 'http://localhost:8000'));
    $runtimeOrigin = smokeLoopbackOrigin((string) config('tools.runtime_origin'));
    $booted = true;
    $workspaceName = 'HTTP smoke '.Str::uuid();
    $anonymous = new SmokeBrowser($baseUrl);
    $anonymous->request('GET', '/up')->expect(200, 'Health endpoint');
    $anonymous->request('GET', '/api/v1/auth/session')->expect(401, 'Unauthenticated session');
    $anonymous->request('GET', '/sanctum/csrf-cookie')->expect(204, 'CSRF cookie');
    $anonymous->json('POST', '/api/v1/auth/login', ['email' => 'csrf-check@example.test', 'password' => 'CsrfProbe123!'], false)->expect(419, 'Missing CSRF token');
    echo "OK health, authentication boundary and CSRF protection\n";

    $password = (string) env('DEMO_PASSWORD', 'LernumiDemo123!');
    foreach (['admin', 'teacher', 'student'] as $account) {
        $browsers[$account] = new SmokeBrowser($baseUrl);
        $browsers[$account]->login($account, $password);
    }
    $admin = $browsers['admin']; $teacher = $browsers['teacher']; $student = $browsers['student'];
    $institutionId = '11111111-1111-4111-8111-111111111111';
    $institutions = $admin->request('GET', '/api/v1/admin/institutions')->expect(200, 'Admin institution list')->json('Institutions');
    smokeAssert(is_array($institutions['data'] ?? null), 'Institution list response had an unexpected shape.');
    $admin->request('GET', '/api/v1/institutions/'.$institutionId)->expect(200, 'Admin institution details');
    $marking = $teacher->request('GET', '/api/v1/institutions/'.$institutionId.'/marking')->expect(200, 'Teacher marking')->json('Marking');
    smokeAssert(is_array($marking['terms'] ?? null) && $marking['terms'] !== [], 'The teacher has no demo marking offering; run the local seeder.');
    $offerings = $student->request('GET', '/api/v1/me/offerings')->expect(200, 'Student offerings')->json('Offerings');
    $published = null;
    foreach ($offerings['offerings'] ?? [] as $offering) {
        if (($offering['institution']['id'] ?? null) !== $institutionId || ! Str::isUuid($offering['id'] ?? '')) continue;
        $details = $student->request('GET', '/api/v1/me/offerings/'.$offering['id'])->expect(200, 'Student offering details')->json('Offering details');
        foreach ($details['offering']['assessments'] ?? [] as $assessment) {
            if (Str::isUuid($assessment['id'] ?? '')) { $published = $assessment['id']; break 2; }
        }
    }
    smokeAssert(is_string($published), 'The student has no published demo assessment; run the local seeder.');
    $student->request('GET', '/api/v1/me/assessments/'.$published)->expect(200, 'Published assessment details');
    echo "OK admin, teacher and student permissions and seeded learning data\n";

    $created = $student->json('POST', '/api/v1/workspaces', ['name' => $workspaceName])->expect(201, 'Temporary personal workspace')->json('Workspace');
    $workspaceId = $created['data']['id'] ?? null;
    smokeAssert(is_string($workspaceId) && Str::isUuid($workspaceId) && ($created['data']['kind'] ?? null) === 'PERSONAL', 'Workspace response had an unexpected shape.');
    $path = '/api/v1/workspaces/'.$workspaceId.'/documents';
    $bytes = str_repeat("Lernumi HTTP smoke file\n", (int) ceil(3 * 1024 * 1024 / 24));
    $bytes = substr($bytes.str_repeat('x', 3 * 1024 * 1024), 0, 3 * 1024 * 1024);
    $originalHash = hash('sha256', $bytes);
    $replacement = "Smoke overwrite preserves whitespace.\n  last line  \n";
    $hashes = [$originalHash, hash('sha256', $replacement), hash('sha256', 'stale overwrite')];
    $boundary = 'LernumiSmoke'.bin2hex(random_bytes(16));
    $multipart = '--'.$boundary."\r\nContent-Disposition: form-data; name=\"entryKind\"\r\n\r\nFILE\r\n"
        .'--'.$boundary."\r\nContent-Disposition: form-data; name=\"file\"; filename=\"smoke-upload.bin\"\r\nContent-Type: application/octet-stream\r\n\r\n"
        .$bytes."\r\n--".$boundary."--\r\n";
    $uploaded = $student->request('POST', $path, $multipart, ['Content-Type' => 'multipart/form-data; boundary='.$boundary])->expect(201, '3 MiB multipart upload')->json('Upload');
    $documentId = $uploaded['data']['id'] ?? null;
    smokeAssert(is_string($documentId) && Str::isUuid($documentId) && ($uploaded['data']['contentHash'] ?? null) === $originalHash
        && ($uploaded['data']['sizeBytes'] ?? null) === 3 * 1024 * 1024, 'Uploaded file metadata did not match the 3 MiB payload.');
    $contentPath = $path.'/'.$documentId.'/content';
    $download = $student->request('GET', $contentPath)->expect(200, 'File download');
    $etag = '"'.$originalHash.'"';
    smokeAssert(hash('sha256', $download->body) === $originalHash && $download->header('ETag') === $etag, 'Downloaded bytes or ETag did not match the upload.');
    $updated = $student->request('PUT', $contentPath, $replacement, ['Content-Type' => 'text/plain', 'If-Match' => $etag])->expect(200, 'ETag overwrite');
    smokeAssert($updated->header('ETag') === '"'.$hashes[1].'"', 'Overwrite did not return the replacement ETag.');
    $student->request('PUT', $contentPath, 'stale overwrite', ['Content-Type' => 'text/plain', 'If-Match' => $etag])->expect(412, 'Stale ETag overwrite');
    $after = $student->request('GET', $contentPath)->expect(200, 'File after stale overwrite');
    smokeAssert($after->body === $replacement, 'Raw overwrite changed whitespace, or a stale write replaced content.');
    $teacher->request('GET', '/api/v1/workspaces/'.$workspaceId)->expect(404, 'Other user private workspace');
    $teacher->request('GET', $contentPath)->expect(404, 'Other user private file');
    echo "OK 3 MiB multipart upload, checksum, ETag writes and private file access\n";

    $tools = $student->request('GET', '/api/v1/tools?per_page=100')->expect(200, 'Available demo tools')->json('Tools');
    $probe = null;
    foreach ($tools['data'] ?? [] as $tool) if (($tool['code'] ?? null) === 'demo-api-probe') { $probe = $tool; break; }
    smokeAssert(is_array($probe) && Str::isUuid($probe['id'] ?? '') && Str::isUuid($probe['activeVersionId'] ?? ''), 'The demo-api-probe tool is missing; run the local seeder.');
    $grant = $student->json('POST', '/api/tool/v1/sessions', ['mode' => 'PERSONAL', 'workspaceId' => $workspaceId, 'toolId' => $probe['id']])->expect(201, 'Personal runtime launch')->json('Runtime grant');
    smokeAssert(is_string($grant['token'] ?? null) && $grant['token'] !== '', 'Runtime launch did not return a capability token.');
    $bearer = ['Authorization' => 'Bearer '.$grant['token']];
    // A separate cookie jar deliberately sends no platform session or CSRF cookie to the tool hostname.
    $runtime = new SmokeBrowser($runtimeOrigin, $runtimeOrigin);
    $context = $runtime->request('GET', '/api/tool/v1/context', null, $bearer, false)->expect(200, 'Runtime context')->json('Runtime context');
    smokeAssert(($context['workspace']['id'] ?? null) === $workspaceId && ($context['workspace']['kind'] ?? null) === 'PERSONAL', 'The runtime context was not bound to the temporary personal workspace.');
    $runtimeAuth = $runtime->request('POST', '/api/tool/v1/runtime-auth', null, $bearer, false)->expect(200, 'Runtime authentication without platform CSRF')->json('Runtime authentication');
    $assetPath = $runtimeAuth['runtimePath'] ?? null;
    smokeAssert(is_string($assetPath) && str_starts_with($assetPath, '/tools/demo-api-probe/'.$probe['activeVersionId'].'/'), 'Runtime authentication returned an unexpected asset path.');
    $asset = $runtime->request('GET', $assetPath)->expect(200, 'Runtime asset with runtime cookie');
    smokeAssert(str_starts_with($asset->header('Content-Type') ?? '', 'text/html'), 'The runtime asset was not served as HTML.');
    $refresh = $runtime->request('POST', '/api/tool/v1/token', null, $bearer, false)->expect(200, 'Runtime token refresh without platform CSRF')->json('Runtime refresh');
    smokeAssert(is_string($refresh['token'] ?? null) && $refresh['token'] !== '', 'Runtime refresh did not return a capability token.');
    echo "OK personal runtime context, cookie-protected assets and bearer token refresh\n";

    $student->request('DELETE', '/api/v1/workspaces/'.$workspaceId)->expect(204, 'Delete temporary workspace');
    $student->request('GET', '/api/v1/workspaces/'.$workspaceId)->expect(404, 'Deleted workspace');
    $runtime->request('GET', '/api/tool/v1/context', null, $bearer, false)->expect(401, 'Runtime after workspace deletion');
} catch (Throwable $error) {
    $failure = $error instanceof SmokeFailure ? $error->getMessage() : 'Unexpected local smoke failure ('.get_debug_type($error).').';
} finally {
    if ($booted) {
        try { cleanupSmokeWorkspace($workspaceId, $workspaceName, $student, $hashes); }
        catch (Throwable) { $failure ??= 'Temporary workspace/content cleanup failed; inspect this run before retrying.'; }
        foreach (array_reverse($browsers) as $browser) {
            try { $browser->logout(); }
            catch (Throwable) { $failure ??= 'Session logout verification failed.'; }
        }
    }
}

if ($failure !== null) { fwrite(STDERR, 'FAIL '.$failure.PHP_EOL); exit(1); }
echo "OK temporary data cleanup and logout/session invalidation\nLocal HTTP smoke passed.\n";
