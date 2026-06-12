<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\ApiResponse;

class CheckAccountExpiry
{
    // al trait al3zma bta3 Hashing: to keep responses consistent
    use ApiResponse;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If a user is authenticated, check their status
        if ($user) {
            if (! $user->is_active) {
                // Revoke current token immediately for security (if it's a real token)
                if ($user->currentAccessToken() instanceof \Laravel\Sanctum\PersonalAccessToken) {
                    $user->currentAccessToken()->delete();
                }

                return $this->errorResponse('Your account has been deactivated.', 403);
            }

            if ($user->expiry_date && now()->startOfDay()->greaterThan($user->expiry_date)) {
                if ($user->currentAccessToken() instanceof \Laravel\Sanctum\PersonalAccessToken) {
                    $user->currentAccessToken()->delete();
                }

                return $this->errorResponse('Your account has expired. Please contact administration.', 403);
            }
        }

        // Everything is good, let the request pass through to the Controller
        return $next($request);
    }
}
