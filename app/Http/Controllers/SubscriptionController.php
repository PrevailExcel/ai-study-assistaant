<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id'
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        // Cancel existing subscription
        Subscription::where('user_id', Auth::id())->update(['active' => false]);

        // Create new subscription
        $subscription = Subscription::create([
            'user_id' => Auth::id(),
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addDays($plan->duration_days),
            'active' => true
        ]);

        return $this->success(
            [
                'subscription' => $subscription,
                'plan' => $plan
            ],
            'Subscription activated successfully.',
            201
        );
    }

    public function listPlans()
    {
        return Cache::rememberForever('allPlans', function () {

            $plans = Plan::select('id', 'name', 'price', 'currency', 'features', 'duration_days')
                ->orderBy('price', 'asc')
                ->get();

            return $this->success($plans);
        });
    }
}
