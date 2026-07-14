<?php

namespace Veekthoven\CashierBachs;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Veekthoven\CashierBachs\Exceptions\BachsApiError;

class BachsApi
{
    /**
     * Get a fresh pending request configured for the Bachs API.
     */
    protected function request(): PendingRequest
    {
        return Http::withToken(Cashier::apiKey())
            ->baseUrl(Cashier::apiUrl().'/v1')
            ->acceptJson()
            ->withUserAgent('Laravel-Cashier-Bachs/'.Cashier::VERSION);
    }

    /**
     * Perform a GET request against the Bachs API.
     */
    public function get(string $uri, array $query = []): array
    {
        return $this->respond($this->request()->get($uri, $query));
    }

    /**
     * Perform a POST request against the Bachs API.
     */
    public function post(string $uri, array $payload = []): array
    {
        return $this->respond($this->request()->post($uri, $payload));
    }

    /**
     * Perform a PATCH request against the Bachs API.
     */
    public function patch(string $uri, array $payload = []): array
    {
        return $this->respond($this->request()->patch($uri, $payload));
    }

    /**
     * Perform a PUT request against the Bachs API.
     */
    public function put(string $uri, array $payload = []): array
    {
        return $this->respond($this->request()->put($uri, $payload));
    }

    /**
     * Perform a DELETE request against the Bachs API.
     */
    public function delete(string $uri, array $payload = []): array
    {
        return $this->respond($this->request()->send('DELETE', $uri, ['json' => $payload]));
    }

    /**
     * Handle the Bachs API response, throwing an exception on failure.
     *
     * @throws BachsApiError
     */
    protected function respond(Response $response): array
    {
        if ($response->failed()) {
            throw BachsApiError::fromResponse($response);
        }

        return $response->json() ?? [];
    }

    // Customers...

    public function createCustomer(array $payload): array
    {
        return $this->post('/customers', $payload);
    }

    public function getCustomer(string $customerId): array
    {
        return $this->get("/customers/{$customerId}");
    }

    public function updateCustomer(string $customerId, array $payload): array
    {
        return $this->patch("/customers/{$customerId}", $payload);
    }

    public function listCustomers(array $query = []): array
    {
        return $this->get('/customers', $query);
    }

    // Checkout sessions...

    public function createCheckoutSession(array $payload): array
    {
        return $this->post('/checkout-sessions', $payload);
    }

    public function getCheckoutSession(string $checkoutId): array
    {
        return $this->get("/checkout-sessions/{$checkoutId}");
    }

    // Subscriptions...

    public function getSubscription(string $subscriptionId): array
    {
        return $this->get("/subscriptions/{$subscriptionId}");
    }

    public function listSubscriptions(array $query = []): array
    {
        return $this->get('/subscriptions', $query);
    }

    public function updateSubscription(string $subscriptionId, array $payload): array
    {
        return $this->patch("/subscriptions/{$subscriptionId}", $payload);
    }

    public function cancelSubscription(string $subscriptionId, array $payload = []): array
    {
        return $this->delete("/subscriptions/{$subscriptionId}", $payload);
    }

    // Products...

    public function getProduct(string $productId): array
    {
        return $this->get("/products/{$productId}");
    }

    public function listProducts(array $query = []): array
    {
        return $this->get('/products', $query);
    }

    // Payments...

    public function getPayment(string $paymentId): array
    {
        return $this->get("/payments/{$paymentId}");
    }

    public function listPayments(array $query = []): array
    {
        return $this->get('/payments', $query);
    }

    // Refunds...

    public function createRefund(array $payload): array
    {
        return $this->post('/refunds', $payload);
    }

    public function getRefund(string $refundId): array
    {
        return $this->get("/refunds/{$refundId}");
    }

    // Webhook endpoints...

    public function createWebhookEndpoint(array $payload): array
    {
        return $this->post('/webhooks/endpoints', $payload);
    }

    public function listWebhookEndpoints(array $query = []): array
    {
        return $this->get('/webhooks/endpoints', $query);
    }

    public function deleteWebhookEndpoint(string $endpointId): array
    {
        return $this->delete("/webhooks/endpoints/{$endpointId}");
    }
}
