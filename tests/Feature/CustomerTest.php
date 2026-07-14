<?php

use Illuminate\Support\Facades\Http;
use Veekthoven\CashierBachs\Exceptions\CustomerAlreadyCreated;
use Veekthoven\CashierBachs\Exceptions\InvalidCustomer;

it('can create a bachs customer', function () {
    Http::fake([
        'sandbox-api.bachs.io/v1/customers' => Http::response([
            'customer_id' => 'cust_new123',
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
        ], 201),
    ]);

    $user = $this->createUser();

    $customer = $user->createAsBachsCustomer();

    expect($customer['customer_id'])->toBe('cust_new123')
        ->and($user->refresh()->bachs_id)->toBe('cust_new123')
        ->and($user->hasBachsId())->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://sandbox-api.bachs.io/v1/customers'
            && $request['email'] === 'jane@example.com'
            && $request['name'] === 'Jane Doe'
            && $request->header('Authorization')[0] === 'Bearer sk_sandbox_testing_key';
    });
});

it('cannot create a bachs customer twice', function () {
    $user = $this->createCustomer();

    $user->createAsBachsCustomer();
})->throws(CustomerAlreadyCreated::class);

it('returns the existing customer from createOrGetBachsCustomer', function () {
    Http::fake([
        'sandbox-api.bachs.io/v1/customers/cust_123' => Http::response([
            'customer_id' => 'cust_123',
            'email' => 'jane@example.com',
        ]),
    ]);

    $user = $this->createCustomer();

    expect($user->createOrGetBachsCustomer()['customer_id'])->toBe('cust_123');
});

it('throws when retrieving a customer that was never created', function () {
    $user = $this->createUser();

    $user->asBachsCustomer();
})->throws(InvalidCustomer::class);

it('syncs customer details to bachs', function () {
    Http::fake([
        'sandbox-api.bachs.io/v1/customers/cust_123' => Http::response([
            'customer_id' => 'cust_123',
            'email' => 'new@example.com',
            'name' => 'New Name',
        ]),
    ]);

    $user = $this->createCustomer(['email' => 'new@example.com', 'name' => 'New Name']);

    $user->syncBachsCustomerDetails();

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && $request['email'] === 'new@example.com'
            && $request['name'] === 'New Name';
    });
});
