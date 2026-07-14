<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Veekthoven\CashierBachs\Events\WebhookHandled;
use Veekthoven\CashierBachs\Events\WebhookReceived;
use Veekthoven\CashierBachs\Subscription;

function signedWebhookCall($test, array $payload, ?string $secret = null, ?int $timestamp = null)
{
    $body = json_encode($payload);
    $timestamp ??= time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret ?? 'whsec_testing_secret');

    return $test->call('POST', '/bachs/webhook', [], [], [], [
        'HTTP_X-Bachs-Timestamp' => $timestamp,
        'HTTP_X-Bachs-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

function subscriptionCreatedPayload(array $overrides = []): array
{
    return [
        'id' => 'evt_1',
        'type' => 'customer.subscription.created',
        'created_at' => '2026-07-14T12:00:00.000000+00:00',
        'organization_id' => 'org_1',
        'data' => array_merge([
            'subscription_id' => 'sub_new1',
            'customer' => ['customer_id' => 'cust_123', 'email' => 'jane@example.com', 'name' => 'Jane Doe'],
            'product_id' => 'prod_pro',
            'status' => 'active',
            'currency' => 'USD',
            'amount' => '25.00',
            'quantity' => 1,
            'current_period_start' => '2026-07-14T00:00:00Z',
            'current_period_end' => '2026-08-14T00:00:00Z',
            'trial_end' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'metadata' => ['subscription_type' => 'default'],
        ], $overrides),
    ];
}

it('rejects webhooks without a valid signature', function () {
    $response = signedWebhookCall($this, subscriptionCreatedPayload(), 'wrong_secret');

    $response->assertForbidden();
});

it('rejects stale webhook deliveries', function () {
    $response = signedWebhookCall($this, subscriptionCreatedPayload(), null, time() - 4000);

    $response->assertForbidden();
});

it('rejects webhooks missing signature headers', function () {
    $response = $this->postJson('/bachs/webhook', subscriptionCreatedPayload());

    $response->assertForbidden();
});

it('creates a local subscription from customer.subscription.created', function () {
    Event::fake([WebhookReceived::class, WebhookHandled::class]);

    $user = $this->createCustomer();

    $response = signedWebhookCall($this, subscriptionCreatedPayload());

    $response->assertOk();

    $subscription = $user->subscriptions()->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->bachs_id)->toBe('sub_new1')
        ->and($subscription->type)->toBe('default')
        ->and($subscription->status)->toBe('active')
        ->and($subscription->product_id)->toBe('prod_pro')
        ->and($subscription->amount)->toBe('25.00')
        ->and($user->refresh()->subscribed())->toBeTrue();

    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('uses the subscription_type metadata for the local type', function () {
    $user = $this->createCustomer();

    signedWebhookCall($this, subscriptionCreatedPayload([
        'metadata' => ['subscription_type' => 'premium'],
    ]));

    expect($user->subscription('premium'))->not->toBeNull();
});

it('clears a generic trial when a subscription is created', function () {
    $user = $this->createCustomer(['trial_ends_at' => Carbon::now()->addDays(3)]);

    signedWebhookCall($this, subscriptionCreatedPayload());

    expect($user->refresh()->trial_ends_at)->toBeNull();
});

it('ignores subscription events for unknown customers', function () {
    $response = signedWebhookCall($this, subscriptionCreatedPayload([
        'customer' => ['customer_id' => 'cust_unknown'],
    ]));

    $response->assertOk();

    expect(Subscription::count())->toBe(0);
});

it('syncs changes from customer.subscription.updated', function () {
    $user = $this->createCustomer();

    signedWebhookCall($this, subscriptionCreatedPayload());

    $payload = subscriptionCreatedPayload([
        'status' => 'past_due',
        'amount' => '30.00',
    ]);
    $payload['type'] = 'customer.subscription.updated';

    signedWebhookCall($this, $payload);

    $subscription = $user->subscriptions()->first();

    expect($user->subscriptions()->count())->toBe(1)
        ->and($subscription->status)->toBe('past_due')
        ->and($subscription->amount)->toBe('30.00');
});

it('creates the subscription on updated when created was missed', function () {
    $user = $this->createCustomer();

    $payload = subscriptionCreatedPayload();
    $payload['type'] = 'customer.subscription.updated';

    signedWebhookCall($this, $payload);

    expect($user->subscriptions()->count())->toBe(1);
});

it('marks the subscription canceled from customer.subscription.deleted', function () {
    $user = $this->createCustomer();

    signedWebhookCall($this, subscriptionCreatedPayload());

    $payload = subscriptionCreatedPayload([
        'status' => 'canceled',
        'canceled_at' => '2026-07-14T12:00:00Z',
    ]);
    $payload['type'] = 'customer.subscription.deleted';

    signedWebhookCall($this, $payload);

    $subscription = $user->subscriptions()->first();

    expect($subscription->status)->toBe('canceled')
        ->and($subscription->canceled())->toBeTrue()
        ->and($user->refresh()->subscribed())->toBeFalse();
});

it('acknowledges unhandled webhook events', function () {
    Event::fake([WebhookReceived::class, WebhookHandled::class]);

    $response = signedWebhookCall($this, [
        'id' => 'evt_2',
        'type' => 'payout.paid',
        'data' => [],
    ]);

    $response->assertOk();

    expect($response->getContent())->toBe('Webhook Received');

    Event::assertDispatched(WebhookReceived::class);
    Event::assertNotDispatched(WebhookHandled::class);
});
