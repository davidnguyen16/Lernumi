<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, 'show']);
Route::middleware('auth:sanctum')->prefix('institutions/{institution}')->whereUuid('institution')->group(function (): void {
    Route::get('logo', [MediaController::class, 'showLogo']);
    Route::post('logo', [MediaController::class, 'storeLogo']);
    Route::delete('logo', [MediaController::class, 'destroyLogo']);
});
