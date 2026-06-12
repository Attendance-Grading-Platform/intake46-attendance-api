<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BillingSnapshot;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BillingController extends Controller
{
    use ApiResponse;

    /**
     * Retrieve billing rollups for the branch.
     *
     * GET /api/v1/billing/branch
     */
    public function branchBilling(Request $request): JsonResponse
    {
        // Secure: Delegates to BillingSnapshotPolicy@viewAny
        $this->authorize('viewAny', BillingSnapshot::class);

        $user = $request->user();

        // Hardened explicit role check to prevent role escalation
        abort_if(
            !in_array($user->role, ['branch_manager', 'track_admin']),
            403,
            'Unauthorized access to billing data.'
        );

        $query = BillingSnapshot::with(['person', 'cohort']);
        
        if ($request->has('cohort_id')) {
            $query->where('cohort_id', $request->cohort_id);
        }

        // Filter: Track Admins only see snapshots for cohorts they manage
        if ($user->role === 'track_admin') {
            $cohortIds = $user->administeredCohorts()->pluck('cohorts.id');
            $query->whereIn('cohort_id', $cohortIds);
        }

        $snapshots = $query->orderBy('created_at', 'desc')->get();

        $totalAmount = 0;
        $totalHours = 0;

        foreach ($snapshots as $snapshot) {
            $totalAmount += $snapshot->total_amount;
            $totalHours += $snapshot->delivered_hours;
        }

        $studentsCount = 0;
        if ($request->has('cohort_id')) {
            $studentsCount = \App\Models\Cohort::find($request->cohort_id)?->students()->count() ?? 0;
        }

        $data = [
            'summary' => [
                'total_delivered_hours' => $totalHours,
                'grand_total_amount' => $totalAmount,
                'students_count' => $studentsCount,
                'cost_per_student' => $studentsCount > 0 ? round($totalAmount / $studentsCount, 2) : 0
            ],
            'snapshots' => $snapshots
        ];

        return $this->successResponse($data, 'Billing data retrieved successfully.');
    }

    /**
     * BIL-1: Trigger fresh generation of billing snapshots for a cohort.
     * Accessible by Track Admins or Branch Managers.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cohort_id' => 'required|exists:cohorts,id',
            'period'    => 'nullable|string|max:50', 
        ]);

        $period = $validated['period'] ?? now()->format('F Y');

        $cohort = \App\Models\Cohort::findOrFail($validated['cohort_id']);
        $this->authorize('update', $cohort);

        // Find all instructors associated with this cohort via pivot
        $instructors = \App\Models\User::where('role', 'instructor')
            ->whereHas('engagements', function ($q) use ($cohort) {
                $q->whereHas('cohorts', function ($sq) use ($cohort) {
                    $sq->where('cohorts.id', $cohort->id);
                });
            })->get();

        $service = new \App\Services\BillingSnapshotService();
        $count = 0;

        foreach ($instructors as $instructor) {
            $service->generate($instructor, $cohort, $period);
            $count++;
        }

        return $this->successResponse(
            ['generated_count' => $count],
            "Created {$count} billing snapshots for cohort: {$cohort->name}."
        );
    }

    // get billing for one instructor
    // GET /api/v1/billing/instructors/{id}
    public function instructorBilling(Request $request, int $id): JsonResponse
    {
        $instructor = \App\Models\User::findOrFail($id);
        $this->authorize('viewAny', BillingSnapshot::class);

        $snapshots = BillingSnapshot::with(['cohort:id,name'])
            ->where('person_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalHours = 0;
        $totalAmount = 0;

        foreach ($snapshots as $snapshot) {
            $totalHours += $snapshot->delivered_hours;
            $totalAmount += $snapshot->total_amount;
        }

        $compensationType = $instructor->compensation_type;
        if ($compensationType == null) {
            $compensationType = 'external';
        }

        $data = [
            'instructor' => [
                'id' => $instructor->id,
                'name' => $instructor->name,
                'compensation_type' => $compensationType,
                'hourly_rate' => $instructor->hourly_rate,
                'fixed_salary' => $instructor->fixed_salary,
            ],
            'summary' => [
                'total_delivered_hours' => $totalHours,
                'total_amount' => $totalAmount,
            ],
            'snapshots' => $snapshots,
        ];

        return $this->successResponse($data, 'Instructor billing retrieved successfully.');
    }
}
