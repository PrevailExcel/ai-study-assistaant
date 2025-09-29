<?php

namespace App\Services;



use App\Models\User;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\SubscriptionTransaction;
use Carbon\Carbon;

class SubscriptionService
{
    protected $paystack;

    public function __construct(PaystackService $paystack)
    {
        $this->paystack = $paystack;
    }

    public function initializeSubscription(User $user, Plan $plan)
    {
        // For free plan, directly activate
        if ($plan->price == 0) {
            return $this->activateFreeSubscription($user, $plan);
        }

        // Initialize Paystack transaction for paid plans
        $response = $this->paystack->initializeTransaction([
            'email' => $user->email,
            'amount' => $plan->price * 100, // Convert to kobo
            'plan' => $plan->paystack_plan_code,
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ],
        ]);

        if ($response['status']) {
            return [
                'success' => true,
                'authorization_url' => $response['data']['authorization_url'],
                'reference' => $response['data']['reference'],
            ];
        }

        return ['success' => false, 'message' => 'Failed to initialize payment'];
    }

    public function verifyAndActivate(string $reference)
    {
        $response = $this->paystack->verifyTransaction($reference);

        if (!$response['status'] || $response['data']['status'] !== 'success') {
            return ['success' => false, 'message' => 'Payment verification failed'];
        }

        $metadata = $response['data']['metadata'];
        $user = User::find($metadata['user_id']);
        $plan = Plan::find($metadata['plan_id']);

        // Cancel existing active subscriptions
        $user->subscriptions()->active()->each->cancel();

        // Create new subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'paystack_subscription_code' => $response['data']['subscription']['subscription_code'] ?? null,
            'paystack_email_token' => $response['data']['subscription']['email_token'] ?? null,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => $plan->interval === 'monthly'
                ? now()->addMonth()
                : now()->addYear(),
        ]);

        // Update user's current subscription
        $user->update(['current_subscription_id' => $subscription->id]);

        // Record transaction
        SubscriptionTransaction::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'paystack_reference' => $reference,
            'amount' => $response['data']['amount'] / 100,
            'status' => 'success',
            'metadata' => $response['data'],
        ]);

        return ['success' => true, 'subscription' => $subscription];
    }

    public function activateFreeSubscription(User $user, Plan $plan)
    {
        // Cancel existing subscriptions
        $user->subscriptions()->active()->each->cancel();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => null, // Free plan doesn't expire
        ]);

        $user->update(['current_subscription_id' => $subscription->id]);

        return ['success' => true, 'subscription' => $subscription];
    }

    public function cancelSubscription(Subscription $subscription)
    {
        if ($subscription->paystack_subscription_code) {
            $this->paystack->cancelSubscription(
                $subscription->paystack_subscription_code,
                $subscription->paystack_email_token
            );
        }

        $subscription->cancel();

        // Downgrade to free plan
        $freePlan = Plan::where('slug', 'free')->first();
        if ($freePlan) {
            $this->activateFreeSubscription($subscription->user, $freePlan);
        }

        return ['success' => true];
    }
}
