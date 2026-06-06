<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScannerController extends Controller
{
    use ApiResponse;

    /**
     * Record a student check-in event from an IoT/QR scanner.
     *
     * POST /api/scan/checkin
     */
    public function checkin(Request $request): JsonResponse
    {
        // TODO: Validate scanner payload & persist check-in record.
        return $this->successResponse(null, 'Check-in endpoint scaffolded.');
    }

    /**
     * Record a student check-out event from an IoT/QR scanner.
     *
     * POST /api/scan/checkout
     */
    public function checkout(Request $request): JsonResponse
    {
        // TODO: Validate scanner payload & persist check-out record.
        return $this->successResponse(null, 'Check-out endpoint scaffolded.');
    }
}
