<?php

namespace App\Services;


use App\Models\User;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\SubscriptionTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    protected $paystack;

    public function __construct(PaystackService $paystack)
    {
        $this->paystack = $paystack;
    }

    public function initializeSubscription(User $user, Plan $plan)
    {
        Log::info('Initializing subscription', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_price' => $plan->price,
        ]);

        // For free plan, directly activate
        if ($plan->price == 0) {
            Log::info('Activating free subscription', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ]);
            return $this->activateFreeSubscription($user, $plan);
        }

        try {
            // Initialize Paystack transaction for paid plans
            $payload = [
                'email' => $user->email,
                'amount' => $plan->price * 100, // Convert to kobo
                'plan' => $plan->paystack_plan_code,
                'callback_url' => route('paystack.callback'),
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                ],
            ];

            Log::info('Sending initialize transaction request to Paystack', $payload);

            $response = $this->paystack->initializeTransaction($payload);

            Log::info('Paystack response received', $response);

            if ($response['status']) {
                Log::info('Paystack transaction initialized successfully', [
                    'reference' => $response['data']['reference'],
                ]);

                return [
                    'success' => true,
                    'authorization_url' => $response['data']['authorization_url'],
                    'reference' => $response['data']['reference'],
                ];
            }

            Log::warning('Paystack initialization failed', [
                'response' => $response,
            ]);

            return ['success' => false, 'message' => 'Failed to initialize payment'];
        } catch (\Exception $e) {
            Log::error('Exception during Paystack initialization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    public function verifyAndActivate(string $reference)
    {
        $response = $this->paystack->verifyTransaction($reference);

        if (!$response['status'] || $response['data']['status'] !== 'success') {
            return view('error');
            return ['success' => false, 'message' => 'Payment verification failed'];
        }

        $metadata = $response['data']['metadata'];
        $user = User::find($metadata['user_id']);
        $plan = Plan::find($metadata['plan_id']);

        // Cancel existing active subscriptions
        $user->subscriptions()
            ->active()
            ->get()
            ->each
            ->cancel();

            Log::info('Activating new subscription', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'response' => $response,
            ]);

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
        // Cancel existing active subscriptions
        $user->subscriptions()
            ->active()
            ->get()
            ->each
            ->cancel();

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
