<?php

namespace Veekthoven\CashierBachs\Concerns;

use Illuminate\Support\Str;
use Veekthoven\CashierBachs\Cashier;
use Veekthoven\CashierBachs\Checkout;

trait PerformsCharges
{
    /**
     * Begin a hosted checkout for one or more one-time products.
     *
     * Accepts a single product ID, a list of product IDs, or a list of
     * ['product_id' => ..., 'quantity' => ..., 'amount' => ...] items.
     */
    public function checkout(string|array $products, array $sessionOptions = []): Checkout
    {
        $customerId = $this->bachsIdOrCreate();

        $cart = collect(is_array($products) ? $products : [$products])
            ->map(function ($product) {
                if (is_string($product)) {
                    return ['product_id' => $product, 'quantity' => 1];
                }

                return $product;
            })->values()->all();

        $payload = array_merge([
            'customer' => ['customer_id' => $customerId],
            'product_cart' => $cart,
        ], $sessionOptions);

        return new Checkout(Cashier::api()->createCheckoutSession($payload));
    }

    /**
     * Refund a payment made by the customer.
     */
    public function refund(string $chargeId, array $options = []): array
    {
        return Cashier::api()->createRefund(array_merge([
            'charge_id' => $chargeId,
            'reference' => 'refund_'.Str::lower((string) Str::ulid()),
        ], $options));
    }
}
