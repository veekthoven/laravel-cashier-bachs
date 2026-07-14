<?php

namespace Veekthoven\CashierBachs\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Veekthoven\CashierBachs\Cashier;
use Veekthoven\CashierBachs\Subscription;
use Veekthoven\CashierBachs\SubscriptionBuilder;

trait ManagesSubscriptions
{
    /**
     * Begin creating a new subscription via a hosted Bachs checkout.
     */
    public function newSubscription(string $type, string $productId): SubscriptionBuilder
    {
        return new SubscriptionBuilder($this, $type, $productId);
    }

    /**
     * Get all of the subscriptions for the billable model.
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Cashier::$subscriptionModel, 'billable')->orderBy('created_at', 'desc');
    }

    /**
     * Get a subscription instance by type.
     */
    public function subscription(string $type = 'default'): ?Subscription
    {
        return $this->subscriptions->where('type', $type)->first();
    }

    /**
     * Determine if the billable model has a valid subscription of the given type.
     */
    public function subscribed(string $type = 'default', ?string $productId = null): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return $productId ? $subscription->hasProduct($productId) : true;
    }

    /**
     * Determine if the billable model has a valid subscription to the given product.
     */
    public function subscribedToProduct(string $productId, string $type = 'default'): bool
    {
        return $this->subscribed($type, $productId);
    }

    /**
     * Determine if the billable model is on trial for the given subscription type.
     */
    public function onTrial(string $type = 'default', ?string $productId = null): bool
    {
        if ($type === 'default' && is_null($productId) && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->onTrial()) {
            return false;
        }

        return $productId ? $subscription->hasProduct($productId) : true;
    }

    /**
     * Determine if the billable model is on a "generic" trial at the model level.
     */
    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the billable model's trial has expired.
     */
    public function hasExpiredTrial(string $type = 'default', ?string $productId = null): bool
    {
        if ($type === 'default' && is_null($productId) && $this->hasExpiredGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->hasExpiredTrial()) {
            return false;
        }

        return $productId ? $subscription->hasProduct($productId) : true;
    }

    /**
     * Determine if the billable model's "generic" trial has expired.
     */
    public function hasExpiredGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Get the ending date of the trial for the given subscription type.
     */
    public function trialEndsAt(string $type = 'default'): ?Carbon
    {
        if ($subscription = $this->subscription($type)) {
            return $subscription->trial_ends_at;
        }

        return $this->trial_ends_at;
    }
}
