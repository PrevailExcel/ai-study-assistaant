<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $limitKey)
    {
        $user = $request->user();
        $subscription = $user->currentSubscription;

        if (!$subscription) {
            return response()->json(['error' => 'No active subscription'], 403);
        }

        $limit = $subscription->plan->getLimit($limitKey);

        if ($limit === null || $limit === -1) {
            // Unlimited or not defined
            return $next($request);
        }

        // You'll need to implement usage tracking based on your needs
        // Example: Check if user has exceeded their limit
        $currentUsage = $this->getCurrentUsage($user, $limitKey);

        if ($currentUsage >= $limit) {
            return response()->json([
                'error' => 'Limit exceeded',
                'message' => "You've reached your {$limitKey} limit of {$limit}",
                'current_usage' => $currentUsage,
                'limit' => $limit
            ], 429);
        }

        return $next($request);
    }

    protected function getCurrentUsage($user, $limitKey)
    {
        // Implement based on what you're limiting
        // Example for API calls:
        // return $user->api_calls()->whereDate('created_at', today())->count();
        return 0;
    }
}
