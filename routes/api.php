<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ScannerController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CohortController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeliverableController;
use App\Http\Controllers\Api\V1\EngagementController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\TrackController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\SubmissionReviewController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ExcuseRequestController;
use App\Http\Controllers\Api\V1\LabGroupController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\SubmissionController;
use App\Http\Controllers\Api\V1\StudentTagController;

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckAccountExpiry;
use App\Http\Middleware\CheckEngagementWindow;

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

        // ── Auth & User Management ───────────────────
        Route::prefix('auth')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])->name('v1.auth.me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('v1.auth.logout');
        });

        // ── Viewing Routes (Bypass Engagement Window) ──
        Route::get('/tracks', [TrackController::class, 'index'])->name('v1.tracks.index');
        Route::get('/tracks/{id}/cohorts', [CohortController::class, 'trackCohorts'])->name('v1.tracks.cohorts');
        Route::get('/cohorts', [CohortController::class, 'index'])->name('v1.cohorts.index');
        Route::get('/cohorts/{cohort}', [CohortController::class, 'show'])->name('v1.cohorts.show');
        Route::get('/cohorts/{cohort}/students', [CohortController::class, 'students'])->name('v1.cohorts.students');

        // ── Engagement-Restricted Core ────────────────
        Route::middleware([CheckEngagementWindow::class])->group(function (): void {
            
            Route::prefix('auth')->group(function (): void {
                // User management endpoints (CRUDS) still restricted
                Route::get('/users', [AuthController::class, 'index'])->name('v1.auth.users.index');
                Route::post('/users', [AuthController::class, 'store'])->name('v1.auth.users.store');
                Route::get('/users/{user}', [AuthController::class, 'show'])->name('v1.auth.users.show');
                Route::put('/users/{user}', [AuthController::class, 'update'])->name('v1.auth.users.update');
                Route::delete('/users/{user}', [AuthController::class, 'destroy'])->name('v1.auth.users.destroy');
            });

            // ── Cohorts Management ───────────────────
            Route::post('/cohorts', [CohortController::class, 'store'])->name('v1.cohorts.store');
            Route::put('/cohorts/{cohort}', [CohortController::class, 'update'])->name('v1.cohorts.update');
            Route::delete('/cohorts/{cohort}', [CohortController::class, 'destroy'])->name('v1.cohorts.destroy');
            Route::put('/cohorts/{cohort}/close', [CohortController::class, 'close'])->name('v1.cohorts.close');
            Route::post('/cohorts/{cohort}/enroll', [CohortController::class, 'enroll'])->name('v1.cohorts.enroll');
        Route::get('/cohorts/{cohort}/grades', [CohortController::class, 'grades'])->name('v1.cohorts.grades');
        Route::post('/cohorts/{cohort}/assign-admin', [CohortController::class, 'assignAdmin'])->name('v1.cohorts.assign-admin');
        Route::get('/cohorts/{cohort}/lab-groups', [LabGroupController::class, 'index'])->name('v1.lab-groups.index');

        // ── Lab Groups ──────────────────────────────
        Route::post('/cohorts/{cohort}/lab-groups', [LabGroupController::class, 'store'])->name('v1.lab-groups.store');
        Route::get('/lab-groups/{labGroup}', [LabGroupController::class, 'show'])->name('v1.lab-groups.show');
        Route::post('/lab-groups/{labGroup}/instructors', [LabGroupController::class, 'assignInstructors'])->name('v1.lab-groups.assign-instructors');
        Route::post('/lab-groups/{labGroup}/students', [LabGroupController::class, 'assignStudents'])->name('v1.lab-groups.assign-students');
        Route::delete('/lab-groups/{labGroup}/students/{studentId}', [LabGroupController::class, 'removeStudent'])->name('v1.lab-groups.remove-student');

        // ── Dashboards & Analytics ────────────────────────
        Route::get('/me/instructor-dashboard', [DashboardController::class, 'instructorDashboard'])->name('v1.me.instructor-dashboard');

        // ── Courses ─────────────────────────────────
        Route::get('/cohorts/{cohort}/courses', [CourseController::class, 'index'])->name('v1.courses.index');
        Route::post('/cohorts/{cohort}/courses', [CourseController::class, 'store'])->name('v1.courses.store');
        Route::apiResource('courses', CourseController::class)->only(['update', 'destroy'])->names('v1.courses');
        Route::post('/courses/{course}/components', [CourseController::class, 'storeComponent'])->name('v1.course-components.store');
        Route::put('/course-components/{component}', [CourseController::class, 'updateComponent'])->name('v1.course-components.update');
        Route::delete('/course-components/{component}', [CourseController::class, 'destroyComponent'])->name('v1.course-components.destroy');

        // ── Grades ──────────────────────────────────
        Route::prefix('grades')->group(function (): void {
            Route::get('/', [GradeController::class, 'index'])->name('v1.grades.index');
            Route::post('/', [GradeController::class, 'store'])->name('v1.grades.store');
            Route::get('/{grade}', [GradeController::class, 'show'])->name('v1.grades.show');
            Route::put('/{grade}', [GradeController::class, 'update'])->name('v1.grades.update');
            Route::patch('/{grade}/override', [GradeController::class, 'override'])->middleware('role:track_admin')->name('v1.grades.override');
        });
        Route::get('/students/{id}/grades', [GradeController::class, 'studentGrades'])->name('v1.students.grades');
        // Detailed breakdown for a specific student (from release branch)
        Route::get('/students/{id}/grades-detail', [SubmissionReviewController::class, 'studentGradesDetail'])->name('v1.submissions.student.grades-detail');

        // ── Submissions & Grading Queue ─────────────
        Route::get('/submissions/queue', [SubmissionReviewController::class, 'queue'])->name('v1.submissions.queue');
        Route::get('/submissions/stats', [SubmissionReviewController::class, 'stats'])->name('v1.submissions.stats');
        
        Route::get('/deliverables/{id}', [SubmissionReviewController::class, 'show'])->name('v1.deliverables.show');
        Route::put('/deliverables/{id}/grade', [SubmissionReviewController::class, 'update'])->name('v1.deliverables.grade');

        // ── Engagements & Sessions ──────────────────
        Route::apiResource('engagements', EngagementController::class)->names('v1.engagements');
        Route::post('/engagements/{engagement}/sessions', [SessionController::class, 'store'])->name('v1.sessions.store');
        Route::get('/engagements/{engagement}/sessions', [SessionController::class, 'index'])->name('v1.sessions.index');
        Route::get('/engagements/{engagement}/deliverables', [SubmissionReviewController::class, 'engagementDeliverables'])->name('v1.engagements.deliverables');
        
        Route::get('/sessions/active', [SessionController::class, 'active'])->name('v1.sessions.active');
        Route::get('/sessions/{session}', [SessionController::class, 'show'])->name('v1.sessions.show');
        Route::patch('/sessions/{session}', [SessionController::class, 'update'])->name('v1.sessions.update');
        Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('v1.sessions.destroy');
        Route::get('/sessions/{session}/attendance', [AttendanceController::class, 'sessionAttendance'])->name('v1.sessions.attendance');
        Route::post('/sessions/{session}/mark-absent', [AttendanceController::class, 'markAbsent'])->name('v1.sessions.mark-absent');

        // ── Announcements ────────────────────────────
        Route::get('/cohorts/{cohort}/announcements', [AnnouncementController::class, 'index'])->name('v1.cohorts.announcements.index');
        Route::post('/cohorts/{cohort}/announcements', [AnnouncementController::class, 'store'])->name('v1.cohorts.announcements.store');
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('v1.announcements.show');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('v1.announcements.update');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('v1.announcements.destroy');

        // ── Student Tags & Notes ─────────────────────
        Route::get('/students/{id}/tags', [StudentTagController::class, 'index'])->name('v1.students.tags.index');
        Route::post('/students/{id}/tags', [StudentTagController::class, 'store'])->name('v1.students.tags.store');
        Route::delete('/students/{id}/tags/{tag}', [StudentTagController::class, 'destroy'])->name('v1.students.tags.destroy');
        Route::get('/students/{id}/notes', [StudentTagController::class, 'listNotes'])->name('v1.students.notes.index');
        Route::post('/students/{id}/notes', [StudentTagController::class, 'storeNote'])->name('v1.students.notes.store');

        // ── Student Portal ───────────────────────────
        Route::prefix('me')->group(function (): void {
            Route::get('/attendance', [AttendanceController::class, 'studentAttendance'])->defaults('id', null)->name('v1.me.attendance');
            Route::get('/ledger', [AttendanceController::class, 'studentLedger'])->name('v1.me.ledger');
            Route::get('/grades', [GradeController::class, 'index'])->name('v1.me.grades');
            Route::get('/excuses', [ExcuseRequestController::class, 'index'])->name('v1.me.excuses');
            Route::post('/excuses', [ExcuseRequestController::class, 'store'])->name('v1.me.excuses.store');
            Route::get('/absent-sessions', [AttendanceController::class, 'absentSessions'])->name('v1.me.absent-sessions');
            Route::post('/deliverables', [SubmissionController::class, 'store'])->name('v1.me.submissions.store');
            Route::get('/announcements', [AnnouncementController::class, 'myAnnouncements'])->name('v1.me.announcements');
            Route::get('/progress', [\App\Http\Controllers\Api\V1\ProgressController::class, 'studentProgress'])->name('v1.me.progress');
            Route::get('/deliverables', [SubmissionController::class, 'index'])->name('v1.me.submissions.index');
        });

        // ── Excuse Requests ──────────────────────────
        Route::apiResource('excuses', ExcuseRequestController::class)->names('v1.excuses')->parameters(['excuses' => 'excuse']);
        Route::put('/excuses/{excuse}/approve', [ExcuseRequestController::class, 'review'])->defaults('status', 'approved')->name('v1.excuses.approve');
        Route::put('/excuses/{excuse}/reject', [ExcuseRequestController::class, 'review'])->defaults('status', 'rejected')->name('v1.excuses.reject');

        // ── Attendance (ATT-1, ATT-4) ────────────────────
        Route::post('/attendance/scan', [AttendanceController::class, 'scan'])->name('v1.attendance.scan');

        // ── Billing ──────────────────────────────────
        Route::prefix('billing')->group(function (): void {
            Route::get('/rollup', [BillingController::class, 'branchBilling'])->name('v1.billing.rollup');
            Route::post('/rollup/generate', [BillingController::class, 'generate'])->name('v1.billing.generate');
            Route::get('/instructors/{id}', [BillingController::class, 'instructorBilling'])->name('v1.billing.instructor');
        });

        // ── Engagements (ENG-3, ENG-4) ─────────────────
        Route::get('/engagements', [EngagementController::class, 'index'])->name('v1.engagements.index');
        Route::post('/engagements', [EngagementController::class, 'store'])->name('v1.engagements.store');
        Route::get('/engagements/{engagement}', [EngagementController::class, 'show'])->name('v1.engagements.show');

        // ── Sessions (ENG-4: delivered flag) ────────────
        Route::patch('/sessions/{session}', [SessionController::class, 'update'])->name('v1.sessions.update');

        // ── Announcements ────────────────────────────────
        Route::get('/announcements', [AnnouncementController::class, 'instructorIndex'])->name('v1.announcements.index');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('v1.announcements.store');

        // ── Attendance (ATT-1, ATT-4) ────────────────────
        Route::post('/attendance/scan', [AttendanceController::class, 'scan'])->name('v1.attendance.scan');
        Route::get('/students/{id}/attendance', [AttendanceController::class, 'studentAttendance'])->name('v1.students.attendance');


        // ── Analytics ────────────────────────────────
        Route::prefix('analytics')->group(function (): void {
            Route::get('/branch', [AnalyticsController::class, 'branchAnalytics'])->name('v1.analytics.branch');
            Route::get('/cohorts/{cohort}', [AnalyticsController::class, 'summary'])->name('v1.analytics.cohort');
            Route::get('/lab-groups/{labGroup}', [AnalyticsController::class, 'labGroupAnalytics'])->name('v1.analytics.lab-group');
            Route::get('/at-risk/{cohort}', [AnalyticsController::class, 'atRisk'])->name('v1.analytics.at-risk');
        });
        Route::get('/students/{id}/analytics', [AnalyticsController::class, 'studentAnalytics'])->name('v1.students.analytics');
        Route::get('/students/{id}/ledger', [AttendanceController::class, 'studentLedger'])->name('v1.students.ledger');
        Route::get('/students/{id}/attendance', [AttendanceController::class, 'studentAttendance'])->name('v1.students.attendance');
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
