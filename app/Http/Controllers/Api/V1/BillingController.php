<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BillingSnapshot;

class BillingController extends Controller
{
    public function branchBilling(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['branch_manager', 'track_admin'])) {
            return response()->json(['message' => 'Unauthorized access to billing data.'], 403);
        }

        $query = BillingSnapshot::with(['person', 'cohort']);

        // filter data if track admin
        if ($user->role === 'track_admin') {
            $cohortIds = $user->administeredCohorts()->pluck('cohorts.id');
            $query->whereIn('cohort_id', $cohortIds);
        }

        $snapshots = $query->orderBy('created_at', 'desc')->get();

        // get total amounts of delivered hours
        $totalAmount = 0;
        $totalHours = 0;

        foreach ($snapshots as $snapshot) {
            $totalAmount += $snapshot->total_amount;
            $totalHours += $snapshot->delivered_hours;
        }

        return response()->json([
            'message' => 'Billing data retrieved successfully',
            'summary' => [
                'total_delivered_hours' => $totalHours,
                'grand_total_amount' => $totalAmount
            ],
            'snapshots' => $snapshots
        ], 200);
    }
}