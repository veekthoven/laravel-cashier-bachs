<?php

namespace Veekthoven\CashierBachs;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Veekthoven\CashierBachs\Exceptions\SubscriptionUpdateFailure;

/**
 * @property string $bachs_id
 * @property string $type
 * @property string $status
 * @property string|null $product_id
 * @property int $quantity
 * @property string|null $currency
 * @property string|null $amount
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $paused_at
 * @property Carbon|null $ends_at
 */
class Subscription extends Model
{
    const STATUS_TRIALING = 'trialing';

    const STATUS_ACTIVE = 'active';

    const STATUS_PAST_DUE = 'past_due';

    const STATUS_UNPAID = 'unpaid';

    const STATUS_CANCELED = 'canceled';

    const STATUS_PAUSED = 'paused';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'cancel_at_period_end' => 'boolean',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'paused_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Get the billable model related to the subscription.
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user that owns the subscription (alias for billable).
     */
    public function owner(): MorphTo
    {
        return $this->billable();
    }

    /**
     * Determine if the subscription has a specific product.
     */
    public function hasProduct(string $productId): bool
    {
        return $this->product_id === $productId;
    }

    /**
     * Determine if the subscription is valid (provides access to the product).
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->pastDue() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is active.
     */
    public function active(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING]) && ! $this->ended();
    }

    /**
     * Filter query by active.
     */
    public function scopeActive($query): void
    {
        $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_TRIALING]);
    }

    /**
     * Determine if the subscription is within its trial period.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Filter query by on trial.
     */
    public function scopeOnTrial($query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Determine if the subscription's trial has expired.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Determine if the subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    /**
     * Filter query by past due.
     */
    public function scopePastDue($query): void
    {
        $query->where('status', self::STATUS_PAST_DUE);
    }

    /**
     * Determine if the subscription is unpaid.
     */
    public function unpaid(): bool
    {
        return $this->status === self::STATUS_UNPAID;
    }

    /**
     * Determine if the subscription is paused.
     */
    public function paused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Determine if the subscription is recurring and not on trial or canceled.
     */
    public function recurring(): bool
    {
        return ! $this->onTrial() && ! $this->canceled();
    }

    /**
     * Determine if the subscription is no longer active.
     */
    public function canceled(): bool
    {
        return $this->status === self::STATUS_CANCELED || ! is_null($this->ends_at);
    }

    /**
     * Filter query by canceled.
     */
    public function scopeCanceled($query): void
    {
        $query->where('status', self::STATUS_CANCELED)->orWhereNotNull('ends_at');
    }

    /**
     * Determine if the subscription has ended and its grace period expired.
     */
    public function ended(): bool
    {
        return $this->canceled() && ! $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     */
    public function onGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Filter query by on grace period.
     */
    public function scopeOnGracePeriod($query): void
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Swap the subscription to a new product (plan).
     *
     * @throws SubscriptionUpdateFailure
     */
    public function swap(string $productId, array $options = []): static
    {
        $this->guardAgainstUpdatingCanceledSubscription();

        $response = Cashier::api()->updateSubscription($this->bachs_id, array_merge([
            'product_id' => $productId,
        ], $options));

        return $this->syncFromBachsSubscription($response);
    }

    /**
     * Swap the subscription to a new product and invoice immediately.
     *
     * @throws SubscriptionUpdateFailure
     */
    public function swapAndInvoice(string $productId): static
    {
        return $this->swap($productId, ['proration_behavior' => 'invoice_now']);
    }

    /**
     * Swap the subscription to a new product without prorating.
     *
     * @throws SubscriptionUpdateFailure
     */
    public function swapWithoutProration(string $productId): static
    {
        return $this->swap($productId, ['proration_behavior' => 'none']);
    }

    /**
     * Extend (or add) the subscription's trial to the given date.
     */
    public function extendTrial(Carbon $date): static
    {
        $response = Cashier::api()->updateSubscription($this->bachs_id, [
            'trial_end' => $date->toIso8601String(),
        ]);

        return $this->syncFromBachsSubscription($response);
    }

    /**
     * End the subscription's trial immediately and begin billing.
     */
    public function endTrial(): static
    {
        if (is_null($this->trial_ends_at)) {
            return $this;
        }

        $response = Cashier::api()->updateSubscription($this->bachs_id, [
            'trial_end' => Carbon::now()->toIso8601String(),
        ]);

        return $this->syncFromBachsSubscription($response);
    }

    /**
     * Update the payment method billed for the subscription.
     */
    public function updatePaymentMethod(string $paymentMethodId): static
    {
        $response = Cashier::api()->updateSubscription($this->bachs_id, [
            'payment_method_id' => $paymentMethodId,
        ]);

        return $this->syncFromBachsSubscription($response);
    }

    /**
     * Cancel the subscription at the end of the current billing period.
     */
    public function cancel(?string $reason = null): static
    {
        $response = Cashier::api()->cancelSubscription($this->bachs_id, array_filter([
            'cancel_at_period_end' => true,
            'reason' => $reason,
        ], fn ($value) => ! is_null($value)));

        return $this->syncFromBachsSubscription($response);
    }

    /**
     * Cancel the subscription immediately.
     */
    public function cancelNow(?string $reason = null): static
    {
        $response = Cashier::api()->cancelSubscription($this->bachs_id, array_filter([
            'cancel_at_period_end' => false,
            'reason' => $reason,
        ], fn ($value) => ! is_null($value)));

        return $this->syncFromBachsSubscription($response);
    }

    /**
     * Get the subscription as a raw Bachs API payload.
     */
    public function asBachsSubscription(): array
    {
        return Cashier::api()->getSubscription($this->bachs_id);
    }

    /**
     * Sync the local subscription record from a Bachs subscription payload.
     */
    public function syncFromBachsSubscription(array $payload): static
    {
        $this->fill([
            'status' => $payload['status'] ?? $this->status,
            'product_id' => $payload['product_id']
                ?? $payload['product']['id']
                ?? $this->product_id,
            'quantity' => $payload['quantity'] ?? $this->quantity,
            'currency' => $payload['currency'] ?? $this->currency,
            'amount' => $payload['amount'] ?? $this->amount,
            'trial_ends_at' => array_key_exists('trial_end', $payload)
                ? ($payload['trial_end'] ? Carbon::parse($payload['trial_end']) : null)
                : $this->trial_ends_at,
            'current_period_start' => isset($payload['current_period_start'])
                ? Carbon::parse($payload['current_period_start'])
                : $this->current_period_start,
            'current_period_end' => isset($payload['current_period_end'])
                ? Carbon::parse($payload['current_period_end'])
                : $this->current_period_end,
        ]);

        $this->paused_at = ($payload['status'] ?? null) === self::STATUS_PAUSED
            ? ($this->paused_at ?? Carbon::now())
            : null;

        $this->ends_at = $this->resolveEndsAt($payload);

        $this->save();

        return $this;
    }

    /**
     * Resolve the local "ends_at" timestamp from a Bachs subscription payload.
     */
    protected function resolveEndsAt(array $payload): ?Carbon
    {
        if (($payload['status'] ?? null) === self::STATUS_CANCELED) {
            return isset($payload['canceled_at'])
                ? Carbon::parse($payload['canceled_at'])
                : Carbon::now();
        }

        if ($payload['cancel_at_period_end'] ?? false) {
            return isset($payload['current_period_end'])
                ? Carbon::parse($payload['current_period_end'])
                : $this->current_period_end;
        }

        return null;
    }

    /**
     * Ensure the subscription can still be updated through the Bachs API.
     *
     * @throws SubscriptionUpdateFailure
     */
    protected function guardAgainstUpdatingCanceledSubscription(): void
    {
        if ($this->status === self::STATUS_CANCELED) {
            throw SubscriptionUpdateFailure::cannotUpdateCanceledSubscription($this);
        }
    }
}
