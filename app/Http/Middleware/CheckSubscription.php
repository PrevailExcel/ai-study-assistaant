<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle(Request $request, Closure $next, ...$allowedPlans)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $subscription = $user->currentSubscription;

        if (!$subscription || !$subscription->isActive()) {
            return response()->json([
                'error' => 'No active subscription',
                'message' => 'Please subscribe to access this feature'
            ], 403);
        }

        if (!empty($allowedPlans) && !in_array($subscription->plan->slug, $allowedPlans)) {
            return response()->json([
                'error' => 'Upgrade required',
                'message' => 'This feature requires a ' . implode(' or ', $allowedPlans) . ' subscription',
                'current_plan' => $subscription->plan->slug
            ], 403);
        }

        // Attach subscription to request for easy access
        $request->merge(['subscription' => $subscription]);

        return $next($request);
    }
}
