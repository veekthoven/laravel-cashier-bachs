<?php

namespace Veekthoven\CashierBachs\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Veekthoven\CashierBachs\Cashier;
use Veekthoven\CashierBachs\Events\WebhookHandled;
use Veekthoven\CashierBachs\Events\WebhookReceived;
use Veekthoven\CashierBachs\Http\Middleware\VerifyWebhookSignature;
use Veekthoven\CashierBachs\Subscription;

class WebhookController extends Controller
{
    /**
     * Create a new webhook controller instance.
     */
    public function __construct()
    {
        $this->middleware(VerifyWebhookSignature::class);
    }

    /**
     * Handle a Bachs webhook call.
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true) ?: [];

        WebhookReceived::dispatch($payload);

        $method = 'handle'.Str::studly(str_replace('.', '_', $payload['type'] ?? ''));

        if (method_exists($this, $method)) {
            $this->{$method}($payload);

            WebhookHandled::dispatch($payload);

            return new Response('Webhook Handled');
        }

        return new Response('Webhook Received');
    }

    /**
     * Handle customer.subscription.created.
     */
    protected function handleCustomerSubscriptionCreated(array $payload): void
    {
        $data = $payload['data'] ?? [];

        $billable = Cashier::findBillable($data['customer']['customer_id'] ?? null);

        if (! $billable) {
            return;
        }

        $subscription = Cashier::$subscriptionModel::firstOrNew([
            'bachs_id' => $data['subscription_id'],
        ]);

        $subscription->billable()->associate($billable);

        $subscription->type = $subscription->type
            ?? $data['metadata']['subscription_type']
            ?? 'default';

        $subscription->syncFromBachsSubscription($data);

        // A new paid subscription supersedes any generic trial on the billable model...
        if ($billable->getAttribute('trial_ends_at')) {
            $billable->forceFill(['trial_ends_at' => null])->save();
        }
    }

    /**
     * Handle customer.subscription.updated.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): void
    {
        $data = $payload['data'] ?? [];

        if ($subscription = $this->findSubscription($data['subscription_id'] ?? null)) {
            $subscription->syncFromBachsSubscription($data);

            return;
        }

        // If the created event was missed, create the record now...
        $this->handleCustomerSubscriptionCreated($payload);
    }

    /**
     * Handle customer.subscription.deleted.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): void
    {
        $data = $payload['data'] ?? [];

        if ($subscription = $this->findSubscription($data['subscription_id'] ?? null)) {
            $subscription->syncFromBachsSubscription(array_merge($data, [
                'status' => Subscription::STATUS_CANCELED,
            ]));
        }
    }

    /**
     * Find a local subscription by its Bachs subscription ID.
     */
    protected function findSubscription(?string $subscriptionId): ?Subscription
    {
        if (is_null($subscriptionId)) {
            return null;
        }

        return Cashier::$subscriptionModel::where('bachs_id', $subscriptionId)->first();
    }
}
