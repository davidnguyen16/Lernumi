<?php

use App\Http\Controllers\ToolAssetController;
use Illuminate\Support\Facades\Route;

// Only the trusted launcher and isolated, immutable tool assets are served here.
$sessionless = [
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
];
Route::get('tool-runtime/bootstrap', [ToolAssetController::class, 'bootstrap'])->withoutMiddleware($sessionless);
Route::get('tools/{toolCode}/{version}/{assetPath?}', [ToolAssetController::class, 'show'])
    ->whereUuid('version')->where('assetPath', '.*')->withoutMiddleware($sessionless);
