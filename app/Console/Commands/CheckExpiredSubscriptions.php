<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Services\SubscriptionService;

class CheckExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:check-expired';
    protected $description = 'Check and handle expired subscriptions';

    public function handle(SubscriptionService $subscriptionService)
    {
        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expiredSubscriptions->isEmpty()) {
            $this->info('No expired subscriptions found.');
            return;
        }

        $this->info("Found {$expiredSubscriptions->count()} expired subscriptions.");

        $freePlan = Plan::where('slug', 'free')->first();

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);

            // Downgrade to free plan
            if ($freePlan) {
                $subscriptionService->activateFreeSubscription(
                    $subscription->user,
                    $freePlan
                );
            }

            $this->line("Expired subscription {$subscription->id} for user {$subscription->user->email}");
        }

        $this->info('Expired subscriptions processed!');
    }
}
