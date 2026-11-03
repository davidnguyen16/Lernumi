<?php

use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('workspaces', WorkspaceController::class)->whereUuid('workspace');
    Route::prefix('workspaces/{workspace}')->whereUuid('workspace')->group(function (): void {
        Route::get('documents', [WorkspaceController::class, 'indexDocuments']);
        Route::post('documents', [WorkspaceController::class, 'storeDocument']);
        Route::delete('documents', [WorkspaceController::class, 'destroyDocuments']);
        Route::get('documents/{document}', [WorkspaceController::class, 'showDocument'])->whereUuid('document');
        Route::patch('documents/{document}', [WorkspaceController::class, 'updateDocument'])->whereUuid('document');
        Route::delete('documents/{document}', [WorkspaceController::class, 'destroyDocument'])->whereUuid('document');
        Route::get('documents/{document}/content', [WorkspaceController::class, 'showDocument'])->whereUuid('document');
        Route::put('documents/{document}/content', [WorkspaceController::class, 'updateContent'])->whereUuid('document');
    });
});
