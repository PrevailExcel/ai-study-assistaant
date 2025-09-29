<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\SubscriptionTransaction;
use App\Models\WebhookLog;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaystackWebhookController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function handle(Request $request)
    {
        // Create webhook log
        $webhookLog = WebhookLog::create([
            'provider' => 'paystack',
            'event' => $request->input('event', 'unknown'),
            'payload' => $request->all(),
            'status' => 'pending',
        ]);

        // Verify webhook signature
        if (!$this->verifySignature($request)) {
            $webhookLog->markAsFailed('Invalid signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? null;

        try {
            $result = null;

            switch ($event) {
                case 'charge.success':
                    $result = $this->handleChargeSuccess($payload['data']);
                    break;

                case 'subscription.create':
                    $result = $this->handleSubscriptionCreate($payload['data']);
                    break;

                case 'subscription.not_renew':
                    $result = $this->handleSubscriptionNotRenew($payload['data']);
                    break;

                case 'subscription.disable':
                    $result = $this->handleSubscriptionDisable($payload['data']);
                    break;

                case 'subscription.expiring_cards':
                    $result = $this->handleSubscriptionExpiringCards($payload['data']);
                    break;

                case 'invoice.create':
                    $result = $this->handleInvoiceCreate($payload['data']);
                    break;

                case 'invoice.update':
                    $result = $this->handleInvoiceUpdate($payload['data']);
                    break;

                case 'invoice.payment_failed':
                    $result = $this->handleInvoicePaymentFailed($payload['data']);
                    break;

                case 'customeridentification.success':
                    $result = $this->handleCustomerIdentificationSuccess($payload['data']);
                    break;

                case 'customeridentification.failed':
                    $result = $this->handleCustomerIdentificationFailed($payload['data']);
                    break;

                default:
                    Log::info('Unhandled Paystack webhook event', ['event' => $event]);
                    $result = response()->json(['message' => 'Event not handled'], 200);
            }

            $webhookLog->markAsProcessed();
            return $result;

        } catch (\Exception $e) {
            $webhookLog->markAsFailed($e->getMessage());

            Log::error('Paystack webhook error', [
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Verify Paystack webhook signature
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Paystack-Signature');

        if (!$signature) {
            return false;
        }

        $secret = config('services.paystack.secret_key');
        $computedSignature = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($signature, $computedSignature);
    }

    /**
     * Handle successful charge/payment
     */
    protected function handleChargeSuccess(array $data)
    {
        $reference = $data['reference'];
        $amount = $data['amount'] / 100; // Convert from kobo
        $customerEmail = $data['customer']['email'];

        // Find user
        $user = User::where('email', $customerEmail)->first();

        if (!$user) {
            Log::warning('User not found for charge.success', ['email' => $customerEmail]);
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check if already processed
        $existingTransaction = SubscriptionTransaction::where('paystack_reference', $reference)->first();
        if ($existingTransaction) {
            Log::info('Transaction already processed', ['reference' => $reference]);
            return response()->json(['message' => 'Already processed'], 200);
        }

        // Get subscription info
        $subscription = null;
        if (isset($data['metadata']['subscription_id'])) {
            $subscription = Subscription::find($data['metadata']['subscription_id']);
        } elseif (isset($data['plan']['id'])) {
            // Find active subscription for this user
            $subscription = $user->subscriptions()
                ->where('status', 'active')
                ->latest()
                ->first();
        }

        if ($subscription) {
            // Record transaction
            SubscriptionTransaction::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'paystack_reference' => $reference,
                'amount' => $amount,
                'status' => 'success',
                'metadata' => $data,
            ]);

            // Extend subscription expiry for renewals
            if ($subscription->expires_at && $subscription->expires_at->isPast()) {
                // If expired, start from now
                $newExpiryDate = $subscription->plan->interval === 'monthly'
                    ? now()->addMonth()
                    : now()->addYear();
            } else {
                // If active, extend from current expiry
                $newExpiryDate = $subscription->plan->interval === 'monthly'
                    ? $subscription->expires_at->addMonth()
                    : $subscription->expires_at->addYear();
            }

            $subscription->update([
                'status' => 'active',
                'expires_at' => $newExpiryDate,
            ]);

            Log::info('Subscription renewed', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'new_expiry' => $newExpiryDate,
            ]);
        }

        return response()->json(['message' => 'Charge processed successfully'], 200);
    }

    /**
     * Handle subscription creation
     */
    protected function handleSubscriptionCreate(array $data)
    {
        $subscriptionCode = $data['subscription_code'];
        $customerEmail = $data['customer']['email'];
        $planCode = $data['plan']['plan_code'];

        $user = User::where('email', $customerEmail)->first();

        if (!$user) {
            Log::warning('User not found for subscription.create', ['email' => $customerEmail]);
            return response()->json(['message' => 'User not found'], 404);
        }

        $plan = Plan::where('paystack_plan_code', $planCode)->first();

        if (!$plan) {
            Log::warning('Plan not found for subscription.create', ['plan_code' => $planCode]);
            return response()->json(['message' => 'Plan not found'], 404);
        }

        // Check if subscription already exists
        $existingSubscription = Subscription::where('paystack_subscription_code', $subscriptionCode)->first();
        if ($existingSubscription) {
            Log::info('Subscription already exists', ['subscription_code' => $subscriptionCode]);
            return response()->json(['message' => 'Subscription already exists'], 200);
        }

        // Cancel existing active subscriptions
        $user->subscriptions()->active()->each->cancel();

        // Create new subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'paystack_subscription_code' => $subscriptionCode,
            'paystack_email_token' => $data['email_token'] ?? null,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => Carbon::parse($data['next_payment_date']),
        ]);

        // Update user's current subscription
        $user->update(['current_subscription_id' => $subscription->id]);

        Log::info('Subscription created', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'plan' => $plan->slug,
        ]);

        return response()->json(['message' => 'Subscription created successfully'], 200);
    }

    /**
     * Handle subscription not renewing (user cancelled auto-renewal)
     */
    protected function handleSubscriptionNotRenew(array $data)
    {
        $subscriptionCode = $data['subscription_code'];

        $subscription = Subscription::where('paystack_subscription_code', $subscriptionCode)->first();

        if (!$subscription) {
            Log::warning('Subscription not found for subscription.not_renew', [
                'subscription_code' => $subscriptionCode
            ]);
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        // Mark as cancelled but keep active until expiry
        $subscription->update([
            'cancelled_at' => now(),
        ]);

        Log::info('Subscription set to not renew', [
            'subscription_id' => $subscription->id,
            'expires_at' => $subscription->expires_at,
        ]);

        // Optionally notify user
        // Mail::to($subscription->user)->send(new SubscriptionCancellationNotice($subscription));

        return response()->json(['message' => 'Subscription marked for non-renewal'], 200);
    }

    /**
     * Handle subscription disable (immediate cancellation)
     */
    protected function handleSubscriptionDisable(array $data)
    {
        $subscriptionCode = $data['subscription_code'];

        $subscription = Subscription::where('paystack_subscription_code', $subscriptionCode)->first();

        if (!$subscription) {
            Log::warning('Subscription not found for subscription.disable', [
                'subscription_code' => $subscriptionCode
            ]);
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        // Immediately cancel and expire
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'expires_at' => now(),
        ]);

        // Downgrade to free plan
        $freePlan = Plan::where('slug', 'free')->first();
        if ($freePlan) {
            $this->subscriptionService->activateFreeSubscription($subscription->user, $freePlan);
        }

        Log::info('Subscription disabled', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
        ]);

        return response()->json(['message' => 'Subscription disabled successfully'], 200);
    }

    /**
     * Handle expiring cards notification
     */
    protected function handleSubscriptionExpiringCards(array $data)
    {
        foreach ($data as $subscription) {
            $subscriptionCode = $subscription['subscription_code'];
            $customerEmail = $subscription['customer']['email'];

            $user = User::where('email', $customerEmail)->first();

            if ($user) {
                Log::info('Card expiring soon', [
                    'user_id' => $user->id,
                    'subscription_code' => $subscriptionCode,
                ]);

                // Send notification to user to update their card
                // Mail::to($user)->send(new CardExpiringNotice($subscription));
            }
        }

        return response()->json(['message' => 'Expiring cards notification processed'], 200);
    }

    /**
     * Handle invoice creation
     */
    protected function handleInvoiceCreate(array $data)
    {
        $customerEmail = $data['customer']['email'];
        $invoiceCode = $data['invoice_code'];
        $amount = $data['amount'] / 100;

        $user = User::where('email', $customerEmail)->first();

        if (!$user) {
            Log::warning('User not found for invoice.create', ['email' => $customerEmail]);
            return response()->json(['message' => 'User not found'], 404);
        }

        Log::info('Invoice created', [
            'user_id' => $user->id,
            'invoice_code' => $invoiceCode,
            'amount' => $amount,
        ]);

        // Optionally store invoice details or notify user
        // Mail::to($user)->send(new InvoiceCreatedNotice($data));

        return response()->json(['message' => 'Invoice creation processed'], 200);
    }

    /**
     * Handle invoice update
     */
    protected function handleInvoiceUpdate(array $data)
    {
        $invoiceCode = $data['invoice_code'];
        $status = $data['status'];

        Log::info('Invoice updated', [
            'invoice_code' => $invoiceCode,
            'status' => $status,
        ]);

        if ($status === 'paid') {
            // Invoice was paid - usually handled by charge.success
            Log::info('Invoice paid', ['invoice_code' => $invoiceCode]);
        }

        return response()->json(['message' => 'Invoice update processed'], 200);
    }

    /**
     * Handle invoice payment failure
     */
    protected function handleInvoicePaymentFailed(array $data)
    {
        $customerEmail = $data['customer']['email'];
        $subscriptionCode = $data['subscription_code'] ?? null;
        $invoiceCode = $data['invoice_code'];

        $user = User::where('email', $customerEmail)->first();

        if (!$user) {
            Log::warning('User not found for invoice.payment_failed', ['email' => $customerEmail]);
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($subscriptionCode) {
            $subscription = Subscription::where('paystack_subscription_code', $subscriptionCode)->first();

            if ($subscription) {
                // Mark subscription as past_due
                $subscription->update([
                    'status' => 'past_due',
                ]);

                Log::warning('Subscription payment failed', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'invoice_code' => $invoiceCode,
                ]);

                // Notify user about payment failure
                // Mail::to($user)->send(new PaymentFailedNotice($subscription, $data));
            }
        }

        return response()->json(['message' => 'Payment failure processed'], 200);
    }

    /**
     * Handle successful customer identification/verification
     */
    protected function handleCustomerIdentificationSuccess(array $data)
    {
        $customerCode = $data['customer_code'];
        $customerEmail = $data['customer']['email'];

        $user = User::where('email', $customerEmail)->first();

        if ($user) {
            // Update user verification status if you're tracking it
            Log::info('Customer identification successful', [
                'user_id' => $user->id,
                'customer_code' => $customerCode,
            ]);
        }

        return response()->json(['message' => 'Customer identification success processed'], 200);
    }

    /**
     * Handle failed customer identification/verification
     */
    protected function handleCustomerIdentificationFailed(array $data)
    {
        $customerCode = $data['customer_code'];
        $customerEmail = $data['customer']['email'];

        $user = User::where('email', $customerEmail)->first();

        if ($user) {
            Log::warning('Customer identification failed', [
                'user_id' => $user->id,
                'customer_code' => $customerCode,
            ]);

            // Notify user or take appropriate action
        }

        return response()->json(['message' => 'Customer identification failure processed'], 200);
    }
}

