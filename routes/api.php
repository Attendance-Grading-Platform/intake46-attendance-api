<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ScannerController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CohortController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\EngagementController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\TrackController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\SubmissionReviewController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ExcuseRequestController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\LabGroupController;
use App\Http\Controllers\Api\V1\AnalyticsController;

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
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('password.email');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.reset');
});

// ──────────────────────────────────────────────────────────
// 2. Protected — Versioned API (v1)
// ──────────────────────────────────────────────────────────
Route::prefix('v1')
    ->middleware(['auth:sanctum', CheckAccountExpiry::class])
    ->group(function (): void {

        // Auth actions that require an active session/token
        Route::prefix('auth')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])
                ->name('v1.auth.me');
            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('v1.auth.logout');
        });

        // ── Tracks ───────────────────────────────────────
        Route::get('/tracks', [TrackController::class, 'index'])
            ->name('v1.tracks.index');
        Route::get('/tracks/{track}/cohorts', [CohortController::class, 'trackCohorts'])
            ->name('v1.tracks.cohorts');
        Route::delete('/tracks/{track}', [TrackController::class, 'destroy'])
            ->middleware('role:branch_manager')
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

        // ── Lab Groups (D2) ──────────────────────────────
        Route::post('/cohorts/{cohort}/lab-groups', [LabGroupController::class, 'store'])
            ->name('v1.lab-groups.store');
        Route::get('/lab-groups/{labGroup}', [LabGroupController::class, 'show'])
            ->name('v1.lab-groups.show');
        Route::post('/lab-groups/{labGroup}/instructors', [LabGroupController::class, 'assignInstructors'])
            ->name('v1.lab-groups.assign-instructors');
        Route::post('/lab-groups/{labGroup}/students', [LabGroupController::class, 'assignStudents'])
            ->name('v1.lab-groups.assign-students');

        // ── Analytics & Rollups (ANL-1) ──────────────────
        Route::get('/cohorts/{cohort}/analytics', [AnalyticsController::class, 'summary'])
            ->name('v1.analytics.summary');
        Route::post('/cohorts/{cohort}/analytics/sync', [AnalyticsController::class, 'sync'])
            ->name('v1.analytics.sync');

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

        // ── Attendance ───────────────────────────────────
        Route::get('/students/{id}/attendance', [AttendanceController::class, 'studentAttendance'])
            ->name('v1.students.attendance');

        // ── Billing ───────────────────────────────────────
        Route::prefix('billing')->group(function (): void {
            Route::get('/branch', [BillingController::class, 'branchBilling'])
                ->name('v1.billing.branch');
        });

        // excuse requests workflow (EXC-1, EXC-3, ATT-5)
        Route::prefix('excuse-requests')->group(function (): void {
            Route::get('/', [ExcuseRequestController::class, 'index'])
                ->name('v1.excuse-requests.index');
            Route::post('/', [ExcuseRequestController::class, 'store'])
                ->name('v1.excuse-requests.store');
            Route::get('/{excuse}', [ExcuseRequestController::class, 'show'])
                ->name('v1.excuse-requests.show');
            Route::patch('/{excuse}', [ExcuseRequestController::class, 'review'])
                ->name('v1.excuse-requests.review');
            Route::delete('/{excuse}', [ExcuseRequestController::class, 'destroy'])
                ->name('v1.excuse-requests.destroy');
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
