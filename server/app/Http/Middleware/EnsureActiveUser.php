<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');
        abort_if($user && ! $user->is_active, 403, 'This account is disabled.');
        return $next($request);
    }
}
