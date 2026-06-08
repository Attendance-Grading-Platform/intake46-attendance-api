<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Engagement;
use App\Models\EngagementSession;
use Carbon\CarbonPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * EngagementService — encapsulates the transactional creation of an
 * Engagement and its auto-generated sessions.
 *
 * Follows the "Skinny Controller, Fat Service" pattern established
 * by CohortService.
 *
 * @see ENG-3: Engagement creation
 * @see ENG-4: Session auto-generation from days_of_week
 */
class EngagementService
{
    /**
     * Create an Engagement and auto-generate sessions for matching
     * days of the week within the date range.
     *
     * The entire operation is wrapped in a DB transaction so that a
     * failure during session generation rolls back the engagement too.
     *
     * @param  array  $data         Validated engagement attributes.
     * @param  int[]  $daysOfWeek   Day-of-week integers (0=Sun … 6=Sat).
     * @return array{engagement: Engagement, sessions: Collection}
     */
    public function createWithSessions(array $data, array $daysOfWeek): array
    {
        return DB::transaction(function () use ($data, $daysOfWeek): array {

            // ── 1. Create the Engagement record ──────────────
            $engagement = Engagement::create([
                'cohort_id'       => $data['cohort_id'],
                'instructor_id'   => $data['instructor_id'],
                'type'            => $data['type'],
                'start_date'      => $data['start_date'],
                'end_date'        => $data['end_date'],
                'scheduled_hours' => $data['scheduled_hours'],
            ]);

            // ── 2. Auto-set instructor expiry_date ───────────
            // Extend the instructor's account expiry to cover the engagement.
            // Only updates if the engagement end_date is later than the
            // current expiry (or if no expiry is set yet).
            $instructor = $engagement->instructor;
            $endDate    = Carbon::parse($data['end_date']);

            if (! $instructor->expiry_date || $endDate->greaterThan($instructor->expiry_date)) {
                $instructor->update(['expiry_date' => $endDate]);
            }

            // ── 3. Generate sessions for matching days ───────
            $sessions = $this->generateSessions($engagement, $daysOfWeek);

            // Eager-load relationships for the API response
            $engagement->load(['instructor:id,name,email', 'cohort:id,name']);

            return [
                'engagement' => $engagement,
                'sessions'   => $sessions,
            ];
        });
    }

    /**
     * Iterate through every calendar day in the engagement's date range
     * and create an EngagementSession for each day whose day-of-week
     * index appears in $daysOfWeek.
     *
     * Uses CarbonPeriod for a clean, memory-efficient iterator.
     *
     * @param  Engagement  $engagement   The parent engagement.
     * @param  int[]       $daysOfWeek   Day-of-week integers (0=Sun … 6=Sat).
     * @return Collection<int, EngagementSession>
     */
    private function generateSessions(Engagement $engagement, array $daysOfWeek): Collection
    {
        $sessions = collect();

        // CarbonPeriod generates an inclusive date range (start ≤ day ≤ end)
        $period = CarbonPeriod::create(
            $engagement->start_date,
            $engagement->end_date,
        );

        foreach ($period as $date) {
            /** @var Carbon $date */
            // Carbon's dayOfWeek: 0 = Sunday, 1 = Monday, … , 6 = Saturday
            if (in_array($date->dayOfWeek, $daysOfWeek, true)) {
                $sessions->push(
                    EngagementSession::create([
                        'engagement_id' => $engagement->id,
                        'session_date'  => $date->toDateString(),
                        'delivered'     => false,
                    ])
                );
            }
        }

        return $sessions;
    }
}
