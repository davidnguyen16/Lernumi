<?php

use App\Http\Controllers\ToolRuntimeController;
use App\Http\Middleware\AuthenticateToolRuntime;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::post('sessions', [ToolRuntimeController::class, 'launch'])->middleware(['auth:sanctum', 'throttle:60,1']);

// Capability APIs authenticate their JWT on every request, independently of SPA cookies.
// Exclude Sanctum's stateful CSRF pipeline even if a configured SPA domain matches the runtime.
Route::withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->group(function (): void {
    Route::middleware(AuthenticateToolRuntime::class)->group(function (): void {
        Route::post('token', [ToolRuntimeController::class, 'token']);
        Route::post('runtime-auth', [ToolRuntimeController::class, 'runtimeAuth']);
        Route::patch('session', [ToolRuntimeController::class, 'close']);
        Route::get('context', [ToolRuntimeController::class, 'context']);
    });
    Route::middleware(AuthenticateToolRuntime::class.':files:read')->group(function (): void {
        Route::get('documents', [ToolRuntimeController::class, 'documents']);
        Route::get('documents/{documentId}', [ToolRuntimeController::class, 'showDocument'])->whereUuid('documentId');
        Route::get('documents/{documentId}/content', [ToolRuntimeController::class, 'content'])->whereUuid('documentId');
    });
    Route::middleware(AuthenticateToolRuntime::class.':files:write')->group(function (): void {
        Route::post('documents', [ToolRuntimeController::class, 'storeDocument']);
        Route::patch('documents/{documentId}', [ToolRuntimeController::class, 'updateDocument'])->whereUuid('documentId');
        Route::delete('documents/{documentId}', [ToolRuntimeController::class, 'deleteDocument'])->whereUuid('documentId');
        Route::put('documents/{documentId}/content', [ToolRuntimeController::class, 'overwrite'])->whereUuid('documentId');
    });
    Route::middleware(AuthenticateToolRuntime::class.':events:append')->group(function (): void {
        Route::post('events', [ToolRuntimeController::class, 'append']);
        Route::get('events/checkpoint', [ToolRuntimeController::class, 'checkpoint']);
    });
    Route::middleware(AuthenticateToolRuntime::class.':replay:read')->group(function (): void {
        Route::get('replay/sessions', [ToolRuntimeController::class, 'replaySessions']);
        Route::get('replay/sessions/{sessionId}/events', [ToolRuntimeController::class, 'replayEvents'])->whereUuid('sessionId');
    });
});
