<?php

namespace Veekthoven\CashierBachs\Commands;

use Illuminate\Console\Command;
use Veekthoven\CashierBachs\Cashier;

class WebhookCommand extends Command
{
    public $signature = 'cashier:webhook
        {--url= : The URL Bachs should deliver events to}
        {--name=Laravel Cashier : A label for the webhook endpoint}';

    public $description = 'Create the Bachs webhook endpoint required by Cashier';

    /**
     * The webhook events Cashier needs to keep local state in sync.
     */
    protected array $events = [
        'customer.created',
        'customer.updated',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'collection.succeeded',
        'collection.failed',
        'invoice.created',
        'invoice.paid',
        'invoice.payment_failed',
        'refund.created',
        'refund.paid',
        'refund.failed',
    ];

    public function handle(): int
    {
        $endpoint = Cashier::api()->createWebhookEndpoint([
            'name' => $this->option('name'),
            'url' => $this->option('url') ?? route('cashier.webhook'),
            'event_types' => $this->events,
        ]);

        $this->components->info("The Bachs webhook endpoint [{$endpoint['endpoint_id']}] was created successfully.");

        $this->components->warn('The signing secret below is shown only once. Add it to your .env file now:');

        $this->line('BACHS_WEBHOOK_SECRET='.$endpoint['signing_secret']);

        return self::SUCCESS;
    }
}
