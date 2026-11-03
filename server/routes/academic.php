<?php

use App\Http\Controllers\AdminAcademicController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\OfferingController;
use App\Http\Controllers\StudyTermController;
use App\Http\Controllers\StudyTermPeriodController;
use App\Http\Controllers\SubjectController;
use Illuminate\Support\Facades\Route;

// Mounted by api.php inside /api/v1. Implicit bindings enforce every parent-child boundary.
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('institution-directory', [InstitutionController::class, 'directory']);
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::apiResource('institutions', InstitutionController::class)->whereUuid('institution');
        Route::get('subjects', [AdminAcademicController::class, 'subjects']);
        Route::get('terms', [AdminAcademicController::class, 'terms']);
        Route::get('offerings', [AdminAcademicController::class, 'offerings']);
        Route::get('assessments', [AdminAcademicController::class, 'assessments']);
    });
    Route::apiResource('institutions', InstitutionController::class)->only(['index', 'show', 'update'])->whereUuid('institution');
    Route::prefix('institutions/{institution}')->whereUuid('institution')->scopeBindings()->group(function (): void {
        Route::apiResource('subjects', SubjectController::class)->whereUuid('subject');
        Route::apiResource('terms', StudyTermController::class)->whereUuid('term');
        Route::prefix('terms/{term}')->whereUuid('term')->group(function (): void {
            Route::apiResource('periods', StudyTermPeriodController::class)->whereUuid('period');
        });
        Route::apiResource('offerings', OfferingController::class)->whereUuid('offering');
        Route::prefix('offerings/{offering}')->whereUuid('offering')->group(function (): void {
            Route::apiResource('assessments', AssessmentController::class)->whereUuid('assessment');
        });
    });
});
