<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ToolOriginBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        $runtimeHost = strtolower((string) parse_url(config('tools.runtime_origin'), PHP_URL_HOST));
        $platformHost = strtolower((string) parse_url(config('tools.platform_origin'), PHP_URL_HOST));
        if ($runtimeHost && $runtimeHost !== $platformHost && $request->getHost() === $runtimeHost) {
            $allowed = $request->is('tools/*', 'tool-runtime/bootstrap', 'up')
                || ($request->is('api/tool/v1/*') && ! $request->is('api/tool/v1/sessions'));
            abort_unless($allowed, 404);
        }

        return $next($request);
    }
}
