<?php

namespace Veekthoven\CashierBachs\Exceptions;

use Exception;
use Veekthoven\CashierBachs\Subscription;

/** @phpstan-consistent-constructor */
class SubscriptionUpdateFailure extends Exception
{
    /**
     * Create a new exception instance for a subscription that cannot be updated.
     */
    public static function cannotUpdateCanceledSubscription(Subscription $subscription): static
    {
        return new static("The subscription \"{$subscription->bachs_id}\" is canceled and cannot be updated.");
    }
}
