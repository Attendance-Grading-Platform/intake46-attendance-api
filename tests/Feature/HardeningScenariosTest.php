<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Cohort;
use App\Models\Engagement;
use App\Models\EngagementSession;
use App\Models\Track;
use App\Models\User;
use App\Models\AttendanceLedger;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\Grade;
use App\Models\Submission;
use App\Models\ExcuseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardeningScenariosTest extends TestCase
{
    use RefreshDatabase;

    /** SC-12: Account Expiry */
    public function test_expired_account_is_blocked()
    {
        $user = User::factory()->create([
            'expiry_date' => now()->subDay(),
            'role' => 'student',
            'is_active' => true
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Your account has expired. Please contact administration.');
    }

    /** SC-13: One-Active Cohort per Track */
    public function test_cannot_have_two_active_cohorts_per_track()
    {
        $branch = Branch::factory()->create();
        $track = Track::factory()->create(['branch_id' => $branch->id]);

        Cohort::factory()->create([
            'track_id' => $track->id,
            'name' => 'Cohort A',
            'status' => 'active',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Cohort::factory()->create([
            'track_id' => $track->id,
            'name' => 'Cohort B',
            'status' => 'active',
        ]);
    }

    /** SC-15: Instructor Engagement Window */
    public function test_instructor_blocked_outside_engagement_window()
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_active' => true]);
        
        // Engagement in the past
        Engagement::create([
            'instructor_id' => $instructor->id,
            'type' => 'lecture',
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonth(),
            'scheduled_hours' => 18,
            'days_of_week' => ['Monday'],
        ]);

        $this->actingAs($instructor)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertSee('Your teaching engagement window has ended');
    }

    /** SC-3: Attendance Point Integrity (Transactions) */
    public function test_ledger_point_recalculation_integrity()
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $engagement = Engagement::factory()->create(['type' => 'lecture']);
        $session = EngagementSession::create([
            'engagement_id' => $engagement->id,
            'session_date' => now()->toDateString(),
            'delivered' => false
        ]);

        // 1. Initial State
        $ledger = AttendanceLedger::create(['student_id' => $student->id, 'balance' => 250]);
        $this->assertEquals(250, $ledger->balance);

        // 2. Mark Absent (-25)
        $ledger->deductUnexcused($session->id);
        $this->assertEquals(225, $ledger->fresh()->balance);

        // 3. Duplicate deduction attempt (Should be blocked by unique transaction constraint)
        try {
            $ledger->deductUnexcused($session->id);
        } catch (\Exception $e) {}
        $this->assertEquals(225, $ledger->fresh()->balance);

        // 4. Excuse Request Approved
        $excuse = ExcuseRequest::create([
            'student_id' => $student->id,
            'session_id' => $session->id,
            'reason' => 'Medical',
            'status' => 'approved'
        ]);
        
        $ledger->deductExcused($session->id, $excuse->id);
        $this->assertEquals(245, $ledger->fresh()->balance);
    }

    /** SC-16: Grade Weight Locking */
    public function test_cannot_change_component_weight_after_grading()
    {
        // Use Branch Manager to bypass cohort-assignment check
        $admin = User::factory()->create(['role' => 'branch_manager', 'is_active' => true]);
        
        $course = Course::factory()->create();
        $component = CourseComponent::create([
            'course_id' => $course->id,
            'type' => 'lab_deliverable',
            'weight' => 20
        ]);

        // Add a grade
        Grade::create([
            'student_id' => User::factory()->create(['role' => 'student'])->id,
            'course_component_id' => $component->id,
            'raw_score' => 15,
            'raw_max' => 20,
            'weight' => 15.0, 
            'graded_by' => $admin->id
        ]);

        // Attempt to update weight
        $this->actingAs($admin)
            ->putJson("/api/v1/course-components/{$component->id}", ['weight' => 30])
            ->assertStatus(422)
            ->assertSee('Cannot modify component weights after grading has started');
    }

    /** SC-18: Submission Asset Validation */
    public function test_submission_rejects_both_url_and_file()
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $cohort = Cohort::factory()->create(['status' => 'active']);
        $student->enrolledCohorts()->attach($cohort->id);
        
        $course = Course::factory()->create(['cohort_id' => $cohort->id]);
        $component = CourseComponent::create(['course_id' => $course->id, 'type' => 'lab_deliverable', 'weight' => 20]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('lab.pdf', 500);

        $this->actingAs($student)
            ->postJson('/api/v1/me/deliverables', [
                'course_component_id' => $component->id,
                'submission_url' => 'http://github.com/test',
                'submission_file' => $file
            ])
            ->assertStatus(422)
            ->assertSee('You must provide EITHER a URL OR a file, not both');
    }

    /** SC-20: Billing Formula Verification */
    public function test_billing_formula_for_internal_staff()
    {
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'compensation_type' => 'internal',
            'fixed_salary' => 5000,
            'hourly_rate' => 100,
            'is_active' => true
        ]);
        
        $cohort = Cohort::factory()->create();
        
        $engagement = Engagement::create([
            'instructor_id' => $instructor->id,
            'type' => 'lecture',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'scheduled_hours' => 3, 
            'days_of_week' => ['Monday']
        ]);
        $engagement->cohorts()->attach($cohort->id);

        // Create 2 sessions of 3 hours each = 6 hours
        EngagementSession::create([
            'engagement_id' => $engagement->id,
            'session_date' => now()->toDateString(),
            'delivered' => true
        ]);
        EngagementSession::create([
            'engagement_id' => $engagement->id,
            'session_date' => now()->addDay()->toDateString(),
            'delivered' => true
        ]);

        $service = new \App\Services\BillingSnapshotService();
        $snapshot = $service->generate($instructor, $cohort, '2026-06');

        // Formula: 5000 + (6 * 100) = 5600
        $this->assertEquals(5600, $snapshot->total_amount);
        $this->assertEquals(600, $snapshot->hourly_component);
        $this->assertEquals(5000, $snapshot->fixed_salary_component);
    }
}
