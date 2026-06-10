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

        $data = [
            'summary' => [
                'total_delivered_hours' => $totalHours,
                'grand_total_amount' => $totalAmount
            ],
            'snapshots' => $snapshots
        ];

        return $this->successResponse($data, 'Billing data retrieved successfully.');
    }
}
