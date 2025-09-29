<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SubscriptionController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id'
        ]);

        $plan = Plan::find($request->plan_id);
        if (!$plan || !$plan->is_active) {
            return $this->error('Invalid plan selected', 400);
        }
        $result = $this->subscriptionService->initializeSubscription($request->user(), $plan);

        return response()->json($result);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'reference' => 'required|string'
        ]);

        $result = $this->subscriptionService->verifyAndActivate($request->reference);

        return $this->success($result);
    }

    public function current(Request $request)
    {
        $subscription = $request->user()->currentSubscription;

        if (!$subscription) {
            return $this->error(['subscription' => null]);
        }

        return $this->success([
            'subscription' => $subscription->load('plan')
        ]);
    }

    public function cancel(Request $request)
    {
        $subscription = $request->user()->currentSubscription;

        if (!$subscription) {
            return $this->error('No active subscription', 404);
        }

        $result = $this->subscriptionService->cancelSubscription($subscription);

        return $this->success($result);
    }

    public function listPlans()
    {
        return Cache::rememberForever('allPlans', function () {

            $plans = Plan::where('is_active', true)
                ->orderBy('price', 'asc')
                ->get();

            return $this->success($plans);
        });
    }
}
