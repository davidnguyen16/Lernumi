<?php

namespace App\Http\Middleware;

use App\Services\ToolRuntimeService;
use Closure;
use Illuminate\Http\Request;

class AuthenticateToolRuntime
{
    public function __construct(private readonly ToolRuntimeService $runtime) {}

    public function handle(Request $request, Closure $next, ?string $capability = null)
    {
        $this->runtime->resolve($request, $capability, false, $request->isMethod('PATCH') && $request->is('api/tool/v1/session'));
        return $next($request)->header('Cache-Control', 'no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
