<?php

use Illuminate\Support\Facades\Http;
use Veekthoven\CashierBachs\Checkout;

it('creates a checkout session for a subscription', function () {
    Http::fake([
        'sandbox-api.bachs.io/v1/checkout-sessions' => Http::response([
            'checkout_id' => 'chk_123',
            'checkout_url' => 'https://checkout.bachs.io/c/chk_123',
            'status' => 'OPEN',
        ], 201),
    ]);

    $user = $this->createCustomer();

    $checkout = $user->newSubscription('default', 'prod_pro')
        ->quantity(2)
        ->withMetadata(['team_id' => 42])
        ->checkout(['success_url' => 'https://app.test/success']);

    expect($checkout)->toBeInstanceOf(Checkout::class)
        ->and($checkout->id())->toBe('chk_123')
        ->and($checkout->url())->toBe('https://checkout.bachs.io/c/chk_123');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/v1/checkout-sessions')
            && $request['customer'] === ['customer_id' => 'cust_123']
            && $request['product_cart'] === [['product_id' => 'prod_pro', 'quantity' => 2]]
            && $request['metadata']['subscription_type'] === 'default'
            && $request['metadata']['team_id'] === 42
            && $request['success_url'] === 'https://app.test/success';
    });
});

it('creates the bachs customer before checkout when needed', function () {
    Http::fake([
        'sandbox-api.bachs.io/v1/customers' => Http::response(['customer_id' => 'cust_fresh'], 201),
        'sandbox-api.bachs.io/v1/checkout-sessions' => Http::response([
            'checkout_id' => 'chk_1',
            'checkout_url' => 'https://checkout.bachs.io/c/chk_1',
        ], 201),
    ]);

    $user = $this->createUser();

    $user->newSubscription('default', 'prod_pro')->checkout();

    expect($user->refresh()->bachs_id)->toBe('cust_fresh');
});

it('creates a one-time checkout for products', function () {
    Http::fake([
        'sandbox-api.bachs.io/v1/checkout-sessions' => Http::response([
            'checkout_id' => 'chk_once',
            'checkout_url' => 'https://checkout.bachs.io/c/chk_once',
        ], 201),
    ]);

    $user = $this->createCustomer();

    $checkout = $user->checkout(['prod_ebook', ['product_id' => 'prod_course', 'quantity' => 3]]);

    expect($checkout->id())->toBe('chk_once');

    Http::assertSent(function ($request) {
        return $request['product_cart'] === [
            ['product_id' => 'prod_ebook', 'quantity' => 1],
            ['product_id' => 'prod_course', 'quantity' => 3],
        ];
    });
});

it('redirects to the hosted checkout url', function () {
    $checkout = new Checkout([
        'checkout_id' => 'chk_1',
        'checkout_url' => 'https://checkout.bachs.io/c/chk_1',
    ]);

    $response = $checkout->toResponse(request());

    expect($response->getTargetUrl())->toBe('https://checkout.bachs.io/c/chk_1');
});
