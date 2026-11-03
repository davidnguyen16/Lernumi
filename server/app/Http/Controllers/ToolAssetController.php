<?php

namespace App\Http\Controllers;

use App\Exceptions\ToolApiException;
use App\Services\ToolPackageService;
use App\Services\ToolRuntimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Trusted launcher and protected static packages on the dedicated tool hostname. */
class ToolAssetController
{
    public function __construct(private readonly ToolRuntimeService $runtime) {}

    public function bootstrap(Request $request)
    {
        $this->assertOrigin($request);
        $nonce = base64_encode(random_bytes(24));
        $html = <<<'HTML'
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Open Lernumi tool</title>
<body><p id="status">Opening your tool…</p><script nonce="NONCE">
(async () => {
  const credentials = new URLSearchParams(location.hash.slice(1));
  const token = credentials.get('lernumi_token');
  history.replaceState(null, '', location.pathname);
  try {
    if (!token || credentials.get('lernumi_api') !== 'v1') throw new Error('Launch this tool from Lernumi to continue.');
    const response = await fetch('/api/tool/v1/runtime-auth', {method:'POST',credentials:'same-origin',headers:{Authorization:'Bearer '+token,Accept:'application/json'}});
    const result = await response.json();
    if (!response.ok) throw new Error(result.error?.message || 'The tool session is unavailable.');
    if (typeof result.runtimePath !== 'string' || !result.runtimePath.startsWith('/tools/')) throw new Error('The runtime path is invalid.');
    const target = new URL(result.runtimePath, location.origin);
    if (target.origin !== location.origin) throw new Error('The runtime origin is invalid.');
    target.hash = new URLSearchParams({lernumi_token:token,lernumi_api:'v1'}).toString();
    location.replace(target.href);
  } catch (error) { document.getElementById('status').textContent = error.message || 'The tool could not be opened.'; }
})();
</script></body></html>
HTML;
        return response(str_replace('NONCE', $nonce, $html))->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'no-store')->header('X-Content-Type-Options', 'nosniff')->header('Referrer-Policy', 'no-referrer')
            ->header('Content-Security-Policy', "default-src 'none'; script-src 'nonce-{$nonce}'; connect-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
    }

    public function show(Request $request, string $toolCode, string $version, string $assetPath = '')
    {
        $this->assertOrigin($request);
        $runtime = $this->runtime->resolve($request, null, true);
        $runtime->loadMissing('tool', 'toolVersion');
        if ($runtime->tool->code !== $toolCode || $runtime->tool_version_id !== $version)
            throw new ToolApiException('NOT_FOUND', 'This asset is outside the authorized tool version.', 404);
        $path = $assetPath !== '' ? $assetPath : $runtime->toolVersion->entry_path;
        if (! ToolPackageService::safePath($path)) throw new ToolApiException('NOT_FOUND', 'Asset not found.', 404);
        $prefix = $runtime->toolVersion->storage_prefix;
        if (! $prefix || ! ToolPackageService::safePath($prefix)) throw new ToolApiException('NOT_FOUND', 'Tool package not found.', 404);
        $key = $prefix.'/'.$path;
        if (! Storage::disk('local')->exists($key) || Storage::disk('local')->directoryExists($key)) throw new ToolApiException('NOT_FOUND', 'Asset not found.', 404);
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'html', 'htm' => 'text/html; charset=utf-8', 'js', 'mjs' => 'text/javascript; charset=utf-8', 'css' => 'text/css; charset=utf-8',
            'json', 'map' => 'application/json', 'wasm' => 'application/wasm', 'svg' => 'image/svg+xml', 'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon',
            'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'txt' => 'text/plain; charset=utf-8',
            default => 'application/octet-stream',
        };
        return response(Storage::disk('local')->get($key))->header('Content-Type', $mime)
            ->header('Cache-Control', 'no-store')->header('X-Content-Type-Options', 'nosniff')->header('Referrer-Policy', 'no-referrer')
            ->header('Cross-Origin-Resource-Policy', 'same-origin')->header('X-Frame-Options', 'DENY')
            ->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
            ->header('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; worker-src 'self' blob:; frame-src 'none'; frame-ancestors 'none'; object-src 'none'; base-uri 'none'; form-action 'none'");
    }

    private function assertOrigin(Request $request): void
    {
        if (strtolower($request->getSchemeAndHttpHost()) !== strtolower($this->runtime->origins()))
            throw new ToolApiException('AUTHORIZATION_FORBIDDEN', 'Tool packages are served only on the dedicated runtime origin.', 403);
    }
}
