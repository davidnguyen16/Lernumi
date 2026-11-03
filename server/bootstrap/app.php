<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->trimStrings(except: ['content', 'currentPassword', 'passwordConfirmation']);
        $middleware->encryptCookies(except: ['lernumi_tool_runtime']);
        $middleware->prepend(\App\Http\Middleware\ToolOriginBoundary::class);
        $middleware->api(append: [
            \App\Http\Middleware\EnsureActiveUser::class,
            \App\Http\Middleware\ApiResponseHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (\Illuminate\Database\QueryException $exception, Request $request) {
            if (! $request->is('api/*')) return null;
            $state = $exception->errorInfo[0] ?? null;
            if (in_array($state, ['23503', '23505', '23514'], true)) {
                return response()->json([
                    'message' => $state === '23503' ? 'This resource is still referenced by other records.' : 'The operation conflicts with an existing resource or database rule.',
                    'error' => ['code' => $state === '23503' ? 'RESOURCE_IN_USE' : 'RESOURCE_CONFLICT'],
                ], $state === '23514' ? 422 : 409);
            }
            return null;
        });
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (! $request->is('api/tool/v1/*') || $exception instanceof \App\Exceptions\ToolApiException) return null;
            $status = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $exception->getStatusCode() : 500;
            if ($exception instanceof \Illuminate\Validation\ValidationException) $status = 422;
            if ($exception instanceof \Illuminate\Auth\AuthenticationException) $status = 401;
            $code = match ($status) { 401 => 'UNAUTHENTICATED', 403 => 'FORBIDDEN', 404 => 'NOT_FOUND', 422 => 'VALIDATION_ERROR', default => 'INTERNAL_ERROR' };
            $error = ['code' => $code, 'message' => $status >= 500 ? 'The tool request could not be completed.' : ($exception->getMessage() ?: 'The request could not be completed.')];
            if ($exception instanceof \Illuminate\Validation\ValidationException) $error['fields'] = $exception->errors();
            return response()->json(['error' => $error], $status)->header('Cache-Control', 'no-store');
        });
    })->create();
