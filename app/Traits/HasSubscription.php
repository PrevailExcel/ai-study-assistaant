<?php

namespace App\Traits;


use App\Models\Subscription;

/**
 * Trait HasSubscription
 * @package App\Traits
 */
trait HasSubscription
{
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription()
    {
        return $this->belongsTo(Subscription::class, 'current_subscription_id');
    }

    public function hasActiveSubscription(): bool
    {
        return $this->currentSubscription && $this->currentSubscription->isActive();
    }

    public function onPlan(string $planSlug): bool
    {
        return $this->hasActiveSubscription() &&
            $this->currentSubscription->plan->slug === $planSlug;
    }

    public function canAccess(string $feature): bool
    {
        if (!$this->hasActiveSubscription()) {
            return false;
        }

        return $this->currentSubscription->plan->hasFeature($feature);
    }

    public function getLimit(string $limitKey, $default = null)
    {
        if (!$this->hasActiveSubscription()) {
            return $default;
        }

        return $this->currentSubscription->plan->getLimit($limitKey, $default);
    }

    public function subscriptionPlan()
    {
        return $this->currentSubscription?->plan;
    }
}
