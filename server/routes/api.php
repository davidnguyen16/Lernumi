<?php

use App\Http\Controllers\SubjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/institutions/{institution}')
    ->whereUuid('institution')
    ->scopeBindings()
    ->group(function (): void {
        Route::apiResource('subjects', SubjectController::class)
            ->whereUuid('subject');
    });
