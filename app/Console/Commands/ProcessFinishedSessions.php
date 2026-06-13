<?php

namespace App\Console\Commands;

use App\Models\EngagementSession;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessFinishedSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:process-finished-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically mark students absent for sessions that have finished.';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceService $attendanceService)
    {
        $now = now();
        $currentDate = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        // Find sessions that ended today but haven't been fully processed
        // We only look at today's sessions where end_time has passed.
        $finishedSessions = EngagementSession::with('engagement')
            ->where('session_date', $currentDate)
            ->where('end_time', '<', $currentTime)
            ->get();

        $processedCount = 0;

        foreach ($finishedSessions as $session) {
            $engagement = $session->engagement;

            // 1. Get all lab groups for this engagement
            $labGroupIds = DB::table('engagement_cohorts')
                ->where('engagement_id', $engagement->id)
                ->join('lab_groups', 'engagement_cohorts.cohort_id', '=', 'lab_groups.cohort_id')
                ->pluck('lab_groups.id');

            // 2. Get all students in these lab groups
            $studentIds = DB::table('lab_group_students')
                ->whereIn('lab_group_id', $labGroupIds)
                ->distinct()
                ->pluck('user_id');

            // 3. Filter out students who already have an AttendanceRecord or an ExcuseRequest
            $recordedStudentIds = DB::table('attendance_records')
                ->where('session_id', $session->id)
                ->pluck('student_id')
                ->toArray();
                
            $excusedStudentIds = DB::table('excuse_requests')
                ->where('session_id', $session->id)
                ->pluck('student_id')
                ->toArray();

            $unrecordedStudents = $studentIds->diff($recordedStudentIds)->diff($excusedStudentIds);

            // 4. Mark remaining students as absent
            foreach ($unrecordedStudents as $studentId) {
                try {
                    $attendanceService->markAbsent($session->id, $studentId, null);
                    $processedCount++;
                } catch (\Exception $e) {
                    Log::error("Failed to mark student $studentId absent for session {$session->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Processed " . $finishedSessions->count() . " finished sessions. Marked $processedCount students absent.");
        Log::info("attendance:process-finished-sessions completed. Marked $processedCount students absent.");
    }
}
