<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ScannerController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CohortController;
use App\Http\Controllers\Api\V1\TrackController;

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckAccountExpiry;

/*
|--------------------------------------------------------------------------
| API Routes — ITI Attendance & Grading Platform
|--------------------------------------------------------------------------
|
| Three architectural route groups:
|
|  1. /auth    — Public authentication endpoints (login).
|  2. /v1      — Protected API surface behind Sanctum (logout, CRUD).
|  3. /scan    — Public fast-path for IoT / QR scanner devices.
|
| All routes are automatically prefixed with /api by the framework.
|
*/

// ──────────────────────────────────────────────────────────
// 1. Public — Authentication
// ──────────────────────────────────────────────────────────
Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])
        ->name('auth.login');
});

// ──────────────────────────────────────────────────────────
// 2. Protected — Versioned API (v1)
// ──────────────────────────────────────────────────────────
Route::prefix('v1')
    ->middleware(['auth:sanctum', CheckAccountExpiry::class])
    ->group(function (): void {

        // Auth actions that require an active session/token
        Route::prefix('auth')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('v1.auth.logout');
        });

        // ── Tracks ───────────────────────────────────────
        Route::get('/tracks', [TrackController::class, 'index'])
            ->name('v1.tracks.index');

        // ── Cohorts (LC-2) ───────────────────────────────
        Route::get('/cohorts', [CohortController::class, 'index'])
            ->name('v1.cohorts.index');

        Route::post('/cohorts', [CohortController::class, 'store'])
            ->name('v1.cohorts.store');

        Route::get('/cohorts/{cohort}', [CohortController::class, 'show'])
            ->name('v1.cohorts.show');

        Route::put('/cohorts/{cohort}', [CohortController::class, 'update'])
            ->name('v1.cohorts.update');

        Route::post('/cohorts/{cohort}/assign-admin', [CohortController::class, 'assignAdmin'])
            ->name('v1.cohorts.assign-admin');

        // ── Grades ───────────────────────────────────────
        Route::prefix('grades')->group(function (): void {
            // TODO: GradeController CRUD routes
        });

        // ── Billing ──────────────────────────────────────
        Route::prefix('billing')->group(function (): void {
            // TODO: BillingController CRUD routes
        });
    });

// ──────────────────────────────────────────────────────────
// 3. Public Fast-Path — IoT / QR Scanners
// ──────────────────────────────────────────────────────────
Route::prefix('scan')->group(function (): void {
    Route::post('/checkin', [ScannerController::class, 'checkin'])
        ->name('scan.checkin');
    Route::post('/checkout', [ScannerController::class, 'checkout'])
        ->name('scan.checkout');
});
