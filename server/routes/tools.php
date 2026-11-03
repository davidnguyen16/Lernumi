<?php

use App\Http\Controllers\ToolController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('tools', [ToolController::class, 'index']);
    Route::get('tools/{tool}', [ToolController::class, 'show'])->whereUuid('tool');
    Route::prefix('admin')->group(function (): void {
        Route::apiResource('tools', ToolController::class)->whereUuid('tool');
        Route::get('tools/{tool}/versions', [ToolController::class, 'versions'])->whereUuid('tool');
        Route::post('tools/{tool}/versions', [ToolController::class, 'upload'])->whereUuid('tool');
        Route::post('tools/{tool}/versions/{version}/activate', [ToolController::class, 'activate'])->whereUuid(['tool', 'version']);
        Route::get('tools/{tool}/versions/{version}/download', [ToolController::class, 'download'])->whereUuid(['tool', 'version']);
    });
});
