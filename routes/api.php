<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ScannerController;
use App\Http\Controllers\Api\V1\AuthController;
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
// Rate Limiting: max 5 attempts per minute
Route::prefix('auth')->middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
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

        // ── Cohorts ──────────────────────────────────────
        Route::prefix('cohorts')->group(function (): void {
            // TODO: CohortController CRUD routes
        });

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
