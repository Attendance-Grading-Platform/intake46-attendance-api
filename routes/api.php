<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ScannerController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CohortController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\EngagementController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\TrackController;
use App\Http\Controllers\API\Student\GradeController;
use App\Http\Controllers\API\Instructor\SubmissionReviewController;
use App\Http\Controllers\Api\V1\AnnouncementController;

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

        // ── Tracks ───────────────────────────────────────
        Route::get('/tracks', [TrackController::class, 'index'])
            ->name('v1.tracks.index');
        Route::get('/tracks/{track}/cohorts', [CohortController::class, 'trackCohorts'])
            ->name('v1.tracks.cohorts');
        Route::delete('/tracks/{track}', [TrackController::class, 'destroy'])
            ->name('v1.tracks.destroy');

        // ── Cohorts (LC-2) ───────────────────────────────
        Route::get('/cohorts', [CohortController::class, 'index'])
            ->name('v1.cohorts.index');

        Route::post('/cohorts', [CohortController::class, 'store'])
            ->name('v1.cohorts.store');

        Route::get('/cohorts/{cohort}', [CohortController::class, 'show'])
            ->name('v1.cohorts.show');

        Route::put('/cohorts/{cohort}', [CohortController::class, 'update'])
            ->name('v1.cohorts.update');
        Route::post('/cohorts/{cohort}/enroll', [CohortController::class, 'enroll'])
            ->name('v1.cohorts.enroll');
        Route::get('/cohorts/{cohort}/students', [CohortController::class, 'students'])
            ->name('v1.cohorts.students');

        Route::post('/cohorts/{cohort}/assign-admin', [CohortController::class, 'assignAdmin'])
            ->name('v1.cohorts.assign-admin');
        Route::delete('/cohorts/{cohort}', [CohortController::class, 'destroy'])
            ->name('v1.cohorts.destroy');

        // ── Courses (D3) ─────────────────────────────────
        Route::get('/cohorts/{cohort}/courses', [CourseController::class, 'index'])
            ->name('v1.courses.index');
        Route::post('/cohorts/{cohort}/courses', [CourseController::class, 'store'])
            ->name('v1.courses.store');
        Route::put('/courses/{course}', [CourseController::class, 'update'])
            ->name('v1.courses.update');

        Route::post('/courses/{course}/components', [CourseController::class, 'storeComponent'])
            ->name('v1.course-components.store');
        Route::put('/course-components/{component}', [CourseController::class, 'updateComponent'])
            ->name('v1.course-components.update');

        // — Grades (Student)
        Route::prefix('grades')->group(function (): void {
            Route::get('/', [GradeController::class, 'index'])->name('v1.grades.index');
        });

        // — Submissions (Instructor)
        Route::prefix('submissions')->group(function (): void {
            Route::get('/', [SubmissionReviewController::class, 'index'])->name('v1.submissions.index');
            Route::put('/{id}', [SubmissionReviewController::class, 'update'])->name('v1.submissions.update');

            // Complex Endpoint: Detailed breakdown for a specific student
            Route::get('/students/{id}/grades', [SubmissionReviewController::class, 'studentGradesDetail'])->name('v1.submissions.student.grades');
        });

 feat/ENG-ATT-controller
        // ── Engagements (ENG-3, ENG-4) ─────────────────
        Route::get('/engagements', [EngagementController::class, 'index'])
            ->name('v1.engagements.index');
        Route::post('/engagements', [EngagementController::class, 'store'])
            ->name('v1.engagements.store');
        Route::get('/engagements/{engagement}', [EngagementController::class, 'show'])
            ->name('v1.engagements.show');

        // ── Sessions (ENG-4: delivered flag) ────────────
        Route::patch('/sessions/{session}', [SessionController::class, 'update'])
            ->name('v1.sessions.update');
        // ── Announcements ────────────────────────────────
        Route::post('/announcements', [AnnouncementController::class, 'store'])
            ->name('v1.announcements.store');
release

        // — Billing
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
