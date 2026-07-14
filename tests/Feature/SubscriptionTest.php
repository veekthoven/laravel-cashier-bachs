<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Veekthoven\CashierBachs\Exceptions\SubscriptionUpdateFailure;
use Veekthoven\CashierBachs\Subscription;

function subscriptionFor($user, array $attributes = []): Subscription
{
    return $user->subscriptions()->create(array_merge([
        'type' => 'default',
        'bachs_id' => 'sub_123',
        'status' => 'active',
        'product_id' => 'prod_basic',
        'quantity' => 1,
        'currency' => 'USD',
        'amount' => '10.00',
    ], $attributes));
}

it('exposes subscription state helpers', function () {
    $user = $this->createCustomer();

    $subscription = subscriptionFor($user);

    expect($subscription->valid())->toBeTrue()
        ->and($subscription->active())->toBeTrue()
        ->and($subscription->onTrial())->toBeFalse()
        ->and($subscription->canceled())->toBeFalse()
        ->and($subscription->recurring())->toBeTrue()
        ->and($subscription->ended())->toBeFalse();

    expect($user->subscribed())->toBeTrue()
        ->and($user->subscribed('default', 'prod_basic'))->toBeTrue()
        ->and($user->subscribed('default', 'prod_other'))->toBeFalse()
        ->and($user->subscribedToProduct('prod_basic'))->toBeTrue()
        ->and($user->subscription()->is($subscription))->toBeTrue();
});

it('treats a trialing subscription as valid and on trial', function () {
    $user = $this->createCustomer();

    $subscription = subscriptionFor($user, [
        'status' => 'trialing',
        'trial_ends_at' => Carbon::now()->addDays(7),
    ]);

    expect($subscription->onTrial())->toBeTrue()
        ->and($subscription->valid())->toBeTrue()
        ->and($subscription->recurring())->toBeFalse()
        ->and($user->onTrial())->toBeTrue();
});

it('handles grace periods after cancellation', function () {
    $user = $this->createCustomer();

    $subscription = subscriptionFor($user, [
        'ends_at' => Carbon::now()->addDays(10),
    ]);

    expect($subscription->canceled())->toBeTrue()
        ->and($subscription->onGracePeriod())->toBeTrue()
        ->and($subscription->valid())->toBeTrue()
        ->and($subscription->ended())->toBeFalse();

    $subscription->ends_at = Carbon::now()->subDay();

    expect($subscription->onGracePeriod())->toBeFalse()
        ->and($subscription->ended())->toBeTrue()
        ->and($subscription->valid())->toBeFalse();
});

it('supports generic trials on the billable model', function () {
    $user = $this->createUser(['trial_ends_at' => Carbon::now()->addDays(5)]);

    expect($user->onGenericTrial())->toBeTrue()
        ->and($user->onTrial())->toBeTrue()
        ->and($user->trialEndsAt())->not->toBeNull();
});

it('swaps the subscription to a new product', function () {
    Http::fake([
        'sandbox-api.bachs.io/v1/subscriptions/sub_123' => Http::response([
            'subscription_id' => 'sub_123',
            'status' => 'active',
            'product_id' => 'prod_pro',
            'quantity' => 1,
            'currency' => 'USD',
            'amount' => '25.00',
            'trial_end' => null,
            'cancel_at_period_end' => false,
            'current_period_start' => '2026-07-01T00:00:00Z',
            'current_period_end' => '2026-08-01T00:00:00Z',
        ]),
    ]);

    $user = $this->createCustomer();
    $subscription = subscriptionFor($user);

    $subscription->swap('prod_pro');

    expect($subscription->refresh()->product_id)->toBe('prod_pro')
        ->and($subscription->amount)->toBe('25.00');

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && $request['product_id'] === 'prod_pro';
    });
});

it('swaps without proration when requested', function () {
    Http::fake([
        'sandbox-api.bachs.io/v1/subscriptions/sub_123' => Http::response([
            'subscription_id' => 'sub_123',
            'status' => 'active',
            'product_id' => 'prod_pro',
        ]),
    ]);

    $user = $this->createCustomer();
    subscriptionFor($user)->swapWithoutProration('prod_pro');

    Http::assertSent(fn ($request) => $request['proration_behavior'] === 'none');
});

it('cannot swap a canceled subscription', function () {
    $user = $this->createCustomer();

    subscriptionFor($user, ['status' => 'canceled'])->swap('prod_pro');
})->throws(SubscriptionUpdateFailure::class);

it('cancels a subscription at period end', function () {
    Http::fake([
        'sandbox-api.bachs.io/v1/subscriptions/sub_123' => Http::response([
            'subscription_id' => 'sub_123',
            'status' => 'active',
            'cancel_at_period_end' => true,
            'current_period_end' => '2026-08-01T00:00:00Z',
        ]),
    ]);

    $user = $this->createCustomer();
    $subscription = subscriptionFor($user);

    $subscription->cancel();

    expect($subscription->onGracePeriod())->toBeTrue()
        ->and($subscription->ends_at->toDateString())->toBe('2026-08-01');

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && $request['cancel_at_period_end'] === true;
    });
});

it('cancels a subscription immediately', function () {
    Http::fake([
        'sandbox-api.bachs.io/v1/subscriptions/sub_123' => Http::response([
            'subscription_id' => 'sub_123',
            'status' => 'canceled',
            'canceled_at' => '2026-07-14T00:00:00Z',
            'cancel_at_period_end' => false,
        ]),
    ]);

    $user = $this->createCustomer();
    $subscription = subscriptionFor($user);

    $subscription->cancelNow('No longer needed');

    expect($subscription->canceled())->toBeTrue()
        ->and($subscription->status)->toBe('canceled')
        ->and($subscription->ends_at)->not->toBeNull();

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && $request['cancel_at_period_end'] === false
            && $request['reason'] === 'No longer needed';
    });
});

it('extends a trial', function () {
    $trialEnd = Carbon::now()->addDays(30)->startOfSecond();

    Http::fake([
        'sandbox-api.bachs.io/v1/subscriptions/sub_123' => Http::response([
            'subscription_id' => 'sub_123',
            'status' => 'trialing',
            'trial_end' => $trialEnd->toIso8601String(),
        ]),
    ]);

    $user = $this->createCustomer();
    $subscription = subscriptionFor($user);

    $subscription->extendTrial($trialEnd);

    expect($subscription->trial_ends_at->equalTo($trialEnd))->toBeTrue();

    Http::assertSent(fn ($request) => $request['trial_end'] === $trialEnd->toIso8601String());
});

it('updates the payment method on the subscription', function () {
    Http::fake([
        'sandbox-api.bachs.io/v1/subscriptions/sub_123' => Http::response([
            'subscription_id' => 'sub_123',
            'status' => 'active',
        ]),
    ]);

    $user = $this->createCustomer();
    subscriptionFor($user)->updatePaymentMethod('pm_999');

    Http::assertSent(fn ($request) => $request['payment_method_id'] === 'pm_999');
});
