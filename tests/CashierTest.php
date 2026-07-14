<?php

use Veekthoven\CashierBachs\Cashier;

it('resolves the sandbox api url from a sandbox key', function () {
    config()->set('cashier.api_key', 'sk_sandbox_abc123');

    expect(Cashier::apiUrl())->toBe(Cashier::SANDBOX_API_URL)
        ->and(Cashier::usesSandbox())->toBeTrue();
});

it('resolves the production api url from a live key', function () {
    config()->set('cashier.api_key', 'sk_live_abc123');

    expect(Cashier::apiUrl())->toBe(Cashier::PRODUCTION_API_URL)
        ->and(Cashier::usesSandbox())->toBeFalse();
});

it('prefers an explicitly configured api url', function () {
    config()->set('cashier.api_url', 'https://example.test/');

    expect(Cashier::apiUrl())->toBe('https://example.test');
});

it('formats amounts into displayable currency', function () {
    expect(Cashier::formatAmount('10.00', 'USD'))->toContain('10.00');
});

it('supports a custom currency formatter', function () {
    Cashier::formatCurrencyUsing(fn ($amount, $currency) => "{$currency} {$amount}!");

    expect(Cashier::formatAmount('10.00', 'NGN'))->toBe('NGN 10.00!');

    Cashier::formatCurrencyUsing(null);
});

it('finds a billable model by bachs customer id', function () {
    $user = $this->createCustomer(['bachs_id' => 'cust_findme']);

    expect(Cashier::findBillable('cust_findme')?->is($user))->toBeTrue()
        ->and(Cashier::findBillable('cust_missing'))->toBeNull()
        ->and(Cashier::findBillable(null))->toBeNull();
});
