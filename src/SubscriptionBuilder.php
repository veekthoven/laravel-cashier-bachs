<?php

namespace Veekthoven\CashierBachs;

use Illuminate\Database\Eloquent\Model;

class SubscriptionBuilder
{
    /**
     * The quantity of the recurring product to subscribe to.
     */
    protected int $quantity = 1;

    /**
     * Metadata to attach to the checkout session.
     */
    protected array $metadata = [];

    /**
     * Create a new subscription builder instance.
     */
    public function __construct(
        protected Model $billable,
        protected string $type,
        protected string $productId,
    ) {}

    /**
     * Specify the quantity of the recurring product.
     */
    public function quantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Attach metadata to the checkout session.
     */
    public function withMetadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Create a hosted Bachs checkout session to start the subscription.
     *
     * The subscription record itself is created locally once Bachs delivers
     * the "customer.subscription.created" webhook after payment.
     */
    public function checkout(array $sessionOptions = []): Checkout
    {
        $payload = array_merge([
            'customer' => ['customer_id' => $this->billable->bachsIdOrCreate()],
            'product_cart' => [
                [
                    'product_id' => $this->productId,
                    'quantity' => $this->quantity,
                ],
            ],
            'metadata' => array_merge($this->metadata, [
                'subscription_type' => $this->type,
            ]),
        ], $sessionOptions);

        return new Checkout(Cashier::api()->createCheckoutSession($payload));
    }
}
