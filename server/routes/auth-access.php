<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScopeCodeController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

// Mounted by api.php below /api/v1. Browser auth uses CSRF-protected cookie sessions.
Route::middleware('web')->withoutMiddleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class)->prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::middleware(['auth:sanctum', EnsureActiveUser::class])->group(function (): void {
        Route::get('session', [AuthController::class, 'session']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::get('sessions', [AuthController::class, 'sessions']);
        Route::delete('sessions/{session}', [AuthController::class, 'revokeSession']);
        Route::put('password', [AuthController::class, 'password'])->middleware('throttle:5,1');
        Route::post('email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:5,1');
        Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:10,1'])->name('verification.verify');
    });
});

Route::middleware(['auth:sanctum', EnsureActiveUser::class])->group(function (): void {
    Route::get('me', [AuthController::class, 'profile']);
    Route::patch('me', [AuthController::class, 'updateProfile'])->middleware('throttle:10,1');
    Route::post('me/avatar', [AuthController::class, 'avatar']);
    Route::delete('me/avatar', [AuthController::class, 'deleteAvatar']);
    Route::get('users/{user}/avatar', [AuthController::class, 'showAvatar'])->whereUuid('user');
    Route::get('me/role-assignments', [AssignmentController::class, 'mine']);
    Route::get('me/role-requests', [AccessRequestController::class, 'myRoles']);
    Route::get('me/scope-requests', [AccessRequestController::class, 'myScopes']);
    Route::get('auth/tokens', [AuthController::class, 'tokens']);
    Route::post('auth/tokens', [AuthController::class, 'createToken'])->middleware('throttle:5,1');
    Route::delete('auth/tokens/{token}', [AuthController::class, 'deleteToken'])->whereNumber('token');
    Route::get('permissions', [AdminUserController::class, 'permissions']);
    Route::get('admin/audit', [AdminUserController::class, 'audit']);
    Route::apiResource('admin/users', AdminUserController::class)->except('destroy')->parameters(['users' => 'user'])->whereUuid('user');
    Route::post('scope-codes/redeem', [ScopeCodeController::class, 'redeem'])->middleware('throttle:10,1');
    Route::prefix('institutions/{institution}')->whereUuid('institution')->group(function (): void {
        Route::get('requestable-offerings', \App\Http\Controllers\RequestableOfferingController::class);
        Route::get('requestable-roles', [RoleController::class, 'requestable']);
        Route::post('join', [RoleController::class, 'join'])->middleware('throttle:10,1');
        Route::apiResource('roles', RoleController::class)->whereUuid('role');
        Route::get('members', [AssignmentController::class, 'members']);
        Route::get('role-assignments', [AssignmentController::class, 'index']);
        Route::post('role-assignments', [AssignmentController::class, 'store']);
        Route::delete('role-assignments/{assignment}', [AssignmentController::class, 'destroy'])->whereUuid('assignment');
        Route::post('role-assignments/{assignment}/scopes', [AssignmentController::class, 'storeScope'])->whereUuid('assignment');
        Route::delete('role-assignments/{assignment}/scopes/{scope}', [AssignmentController::class, 'destroyScope'])->whereUuid(['assignment', 'scope']);
        Route::get('role-requests', [AccessRequestController::class, 'roles']);
        Route::post('role-requests', [AccessRequestController::class, 'storeRole']);
        Route::patch('role-requests/{application}', [AccessRequestController::class, 'reviewRole'])->whereUuid('application');
        Route::get('scope-requests', [AccessRequestController::class, 'scopes']);
        Route::post('scope-requests', [AccessRequestController::class, 'storeScope']);
        Route::patch('scope-requests/{application}', [AccessRequestController::class, 'reviewScope'])->whereUuid('application');
        Route::get('scope-codes', [ScopeCodeController::class, 'index']);
        Route::post('scope-codes', [ScopeCodeController::class, 'store']);
        Route::delete('scope-codes/{code}', [ScopeCodeController::class, 'destroy'])->whereUuid('code');
        Route::get('audit', [AdminUserController::class, 'audit']);
    });
});
