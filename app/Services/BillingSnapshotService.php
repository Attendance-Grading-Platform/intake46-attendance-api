<?php

namespace App\Services;

use App\Models\User;
use App\Models\Cohort;
use App\Models\Engagement;
use App\Models\BillingSnapshot;

class BillingSnapshotService
{
    /**
     * Generate a billing snapshot for a specific instructor and cohort.
     */
    public function generate(User $instructor, Cohort $cohort, string $period): BillingSnapshot
    {
        // collect all engagements of the instructor in this cohort (via pivot table)
        $engagements = Engagement::with('sessions')
            ->where('instructor_id', $instructor->id)
            ->whereHas('cohorts', function ($q) use ($cohort) {
                $q->where('cohorts.id', $cohort->id);
            })
            ->get();

        // calculate total delivered hours
        $totalDeliveredHours = 0;
        foreach ($engagements as $engagement) {
            $totalDeliveredHours += $engagement->deliveredHours();
        }

        // calculate money based on contract type (internal or external)
        $compensationType = $instructor->compensation_type ?? 'external';
        $fixedSalaryComponent = 0;
        $hourlyComponent = 0;

        if ($compensationType === 'internal') {
            $fixedSalaryComponent = $instructor->fixed_salary ?? 0;
            $hourlyComponent = $totalDeliveredHours * ($instructor->hourly_rate ?? 0);
        } else {
            $hourlyComponent = $totalDeliveredHours * ($instructor->hourly_rate ?? 0);
        }

        $totalAmount = $fixedSalaryComponent + $hourlyComponent;

        return BillingSnapshot::create([
            'person_id' => $instructor->id,
            'cohort_id' => $cohort->id,
            'period' => $period,
            'compensation_type' => $compensationType,
            'delivered_hours' => $totalDeliveredHours,
            'fixed_salary_component' => $fixedSalaryComponent,
            'hourly_component' => $hourlyComponent,
            'total_amount' => $totalAmount,
        ]);
    }
}