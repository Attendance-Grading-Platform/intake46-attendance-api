<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Cohort;
use App\Models\Engagement;
use App\Models\EngagementSession;
use App\Models\StudentTag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class EngagementSeeder extends Seeder
{
    private const ENGAGEMENT_COUNT = 5;

    private const SESSIONS_PER_ENGAGEMENT = 2;

    public function run(): void
    {
        $instructors = User::where('role', 'instructor')->get();
        $cohorts = Cohort::with(['students', 'track'])->get();

        if ($instructors->isEmpty() || $cohorts->isEmpty()) {
            return;
        }

        $now = now();

        $staticInstructor = User::where('email', 'amira.khaled@iti.edu.eg')->first();
        $webCohort = $cohorts->first(
            fn (Cohort $cohort) => str_contains($cohort->track->name, 'Web'),
        );

        if ($staticInstructor && $webCohort) {
            $e = Engagement::create([
                'instructor_id'     => $staticInstructor->id,
                'type'              => 'lab',
                'start_date'        => Carbon::today()->subMonths(1),
                'end_date'          => Carbon::today()->addMonths(2),
                'scheduled_hours'   => 4,
                'hours_per_session' => 4,
            ]);
            $e->cohorts()->attach($webCohort->id);
        }

        for ($i = 0; $i < self::ENGAGEMENT_COUNT; $i++) {

            $engagement = Engagement::create([
                'instructor_id'     => $instructors->random()->id,
                'type'              => ['lecture', 'lab', 'business'][array_rand(['lecture', 'lab', 'business'])],
                'start_date'        => Carbon::today()->subDays(rand(10, 30)),
                'end_date'          => Carbon::today()->addDays(rand(10, 60)),
                'scheduled_hours'   => rand(2, 6),
                'hours_per_session' => rand(2, 6),
            ]);

            $attachedCohort = $cohorts->random();
            $engagement->cohorts()->attach($attachedCohort->id);

            for ($sNum = 1; $sNum <= self::SESSIONS_PER_ENGAGEMENT; $sNum++) {
                $sessionDate = Carbon::today()->subDays($sNum * 3);
                $session = EngagementSession::create([
                    'engagement_id' => $engagement->id,
                    'session_date'  => $sessionDate,
                    'delivered'     => true,
                ]);

                $this->insertAttendanceRecords($session->id, collect([$attachedCohort]), $sessionDate, $now);
            }
        }

        $this->seedStudentTags($now);
    }

    private function insertAttendanceRecords(
        int $sessionId,
        Collection $cohorts,
        Carbon $sessionDate,
        Carbon $now,
    ): void {
        $studentsByTrack = [];
        foreach ($cohorts as $cohort) {
            foreach ($cohort->students as $student) {
                $studentsByTrack[$student->id] = $cohort->track_id;
            }
        }

        $rows = [];
        foreach ($studentsByTrack as $studentId => $trackId) {
            $isPresent = rand(1, 100) <= 85;
            $rows[] = [
                'session_id' => $sessionId,
                'student_id' => $studentId,
                'track_id'   => $trackId,
                'status'     => $isPresent ? 'present' : 'absent',
                'arrived_at' => $isPresent ? $sessionDate->copy()->setTime(9, rand(0, 20)) : null,
                'left_at'    => $isPresent ? $sessionDate->copy()->setTime(13, 0) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            AttendanceRecord::insert($chunk);
        }
    }

    private function seedStudentTags(Carbon $now): void
    {
        $trackAdmin = User::where('role', 'track_admin')->first();
        if (! $trackAdmin) {
            return;
        }

        $tagsList = ['academic_concern', 'attendance_concern', 'top_performer', 'low_performance'];
        $rows = [];

        foreach (User::where('role', 'student')->pluck('id') as $studentId) {
            if (rand(1, 100) > 30) {
                continue;
            }

            $selectedTags = (array) array_rand(array_flip($tagsList), rand(1, 2));
            foreach ($selectedTags as $tagName) {
                $rows[] = [
                    'student_id' => $studentId,
                    'created_by' => $trackAdmin->id,
                    'tag'        => $tagName,
                    'note'       => 'Automatically seeded tag for testing.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            StudentTag::insert($rows);
        }
    }
}
