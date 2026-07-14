<?php

namespace Veekthoven\CashierBachs\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WebhookReceived
{
    use Dispatchable;

    /**
     * Create a new event instance.
     *
     * @param  array  $payload  The full Bachs webhook payload.
     */
    public function __construct(public array $payload) {}
}
