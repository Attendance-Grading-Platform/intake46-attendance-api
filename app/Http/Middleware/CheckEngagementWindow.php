<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\ApiResponse;

/**
 * SC-15: Instructor Engagement Window
 * Blocks instructors from accessing protected API surface (or posting announcements)
 * outside their specific engagement start/end dates.
 */
class CheckEngagementWindow
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // SC-15: This guard applies ONLY to instructors.
        // Students are restricted by cohort end dates (handled via account expiry).
        if (!$user || $user->role !== 'instructor') {
            return $next($request);
        }

        // Branch Managers and Track Admins are immune to engagement window checks
        // as they manage the platform overall.
        
        // Check if instructor has ANY active engagement today.
        // If they are teaching multiple tracks, one active one is enough for API access.
        $hasActiveEngagement = $user->engagements()
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->exists();

        if (!$hasActiveEngagement) {
            return $this->errorResponse(
                'Your teaching engagement window has ended or not yet started. Access restricted.',
                403
            );
        }

        return $next($request);
    }
}
