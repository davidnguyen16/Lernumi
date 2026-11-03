<?php

use App\Http\Controllers\AttemptController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\MarkingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me/offerings', [LearningController::class, 'index']);
    Route::get('me/offerings/{offering}', [LearningController::class, 'show'])->whereUuid('offering');
    Route::get('me/assessments/{assessment}', [LearningController::class, 'assessment'])->whereUuid('assessment');
    Route::get('me/attempts', [AttemptController::class, 'index']);
    Route::post('me/attempts', [AttemptController::class, 'store']);
    Route::get('me/attempts/{attempt}', [AttemptController::class, 'show'])->whereUuid('attempt');
    Route::post('me/attempts/{attempt}/submit', [AttemptController::class, 'submit'])->whereUuid('attempt');

    Route::prefix('me/assessments/{assessment}/groups')->whereUuid('assessment')->group(function (): void {
        Route::get('', [GroupController::class, 'index']);
        Route::post('', [GroupController::class, 'store']);
        Route::get('{group}', [GroupController::class, 'show'])->whereUuid('group');
        Route::match(['put', 'patch'], '{group}', [GroupController::class, 'update'])->whereUuid('group');
        Route::delete('{group}', [GroupController::class, 'destroy'])->whereUuid('group');
    });

    Route::get('me/calendar', [CalendarController::class, 'index']);
    Route::get('me/calendar-events', [CalendarController::class, 'events']);
    Route::post('me/calendar-events', [CalendarController::class, 'store']);
    Route::match(['put', 'patch'], 'me/calendar-events/{event}', [CalendarController::class, 'update'])->whereUuid('event');
    Route::delete('me/calendar-events/{event}', [CalendarController::class, 'destroy'])->whereUuid('event');
    Route::get('me/assessment-plans', [CalendarController::class, 'plans']);
    Route::put('me/assessment-plans/{assessment}', [CalendarController::class, 'plan'])->whereUuid('assessment');
    Route::delete('me/assessment-plans/{assessment}', [CalendarController::class, 'deletePlan'])->whereUuid('assessment');

    Route::prefix('institutions/{institution}')->whereUuid('institution')->group(function (): void {
        Route::get('marking', [MarkingController::class, 'index']);
        Route::get('attempts/{attempt}', [MarkingController::class, 'show'])->whereUuid('attempt');
        Route::post('attempts/{attempt}/marks', [MarkingController::class, 'store'])->whereUuid('attempt');
        Route::patch('attempts/{attempt}/marks/{mark}', [MarkingController::class, 'update'])->whereUuid(['attempt', 'mark']);
        Route::post('attempts/{attempt}/marks/{mark}/submit', [MarkingController::class, 'submit'])->whereUuid(['attempt', 'mark']);
        Route::put('attempts/{attempt}/result', [MarkingController::class, 'finalise'])->whereUuid('attempt');
        Route::delete('attempts/{attempt}/result', [MarkingController::class, 'clearResult'])->whereUuid('attempt');
    });
});
