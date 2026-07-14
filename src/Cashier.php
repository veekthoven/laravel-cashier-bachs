<?php

namespace Veekthoven\CashierBachs;

use Illuminate\Database\Eloquent\Model;

/** @phpstan-consistent-constructor */
class Cashier
{
    /**
     * The Cashier Bachs library version.
     */
    const VERSION = '0.1.0';

    /**
     * The base URL for the production Bachs API.
     */
    const PRODUCTION_API_URL = 'https://api.bachs.io';

    /**
     * The base URL for the sandbox Bachs API.
     */
    const SANDBOX_API_URL = 'https://sandbox-api.bachs.io';

    /**
     * The custom currency formatter registered by the developer.
     *
     * @var callable|null
     */
    protected static $formatCurrencyUsing;

    /**
     * Indicates if Cashier migrations will be run.
     */
    public static bool $runsMigrations = true;

    /**
     * Indicates if Cashier routes will be registered.
     */
    public static bool $registersRoutes = true;

    /**
     * The subscription model class name.
     */
    public static string $subscriptionModel = Subscription::class;

    /**
     * The customer (billable) model class name.
     */
    public static string $customerModel = 'App\\Models\\User';

    /**
     * Get a configured Bachs API client instance.
     */
    public static function api(): BachsApi
    {
        return app(BachsApi::class);
    }

    /**
     * Get the Bachs API key.
     */
    public static function apiKey(): ?string
    {
        return config('cashier.api_key');
    }

    /**
     * Get the base URL for the Bachs API.
     *
     * Resolved from the configured URL, or from the API key prefix when
     * no URL has been explicitly configured.
     */
    public static function apiUrl(): string
    {
        if ($url = config('cashier.api_url')) {
            return rtrim($url, '/');
        }

        if (str_starts_with((string) static::apiKey(), 'sk_sandbox_')) {
            return static::SANDBOX_API_URL;
        }

        return static::PRODUCTION_API_URL;
    }

    /**
     * Determine if the configured API key is a sandbox key.
     */
    public static function usesSandbox(): bool
    {
        return static::apiUrl() === static::SANDBOX_API_URL;
    }

    /**
     * Get the default currency used by Cashier.
     */
    public static function usesCurrency(): string
    {
        return strtoupper(config('cashier.currency', 'USD'));
    }

    /**
     * Set the custom currency formatter.
     */
    public static function formatCurrencyUsing(?callable $callback): void
    {
        static::$formatCurrencyUsing = $callback;
    }

    /**
     * Format the given decimal amount string into a displayable currency.
     */
    public static function formatAmount(string $amount, ?string $currency = null): string
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount, $currency);
        }

        $currency = strtoupper($currency ?? static::usesCurrency());

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter(config('app.locale', 'en'), \NumberFormatter::CURRENCY);

            return $formatter->formatCurrency((float) $amount, $currency);
        }

        return $currency.' '.number_format((float) $amount, 2);
    }

    /**
     * Configure Cashier to not register its migrations.
     */
    public static function ignoreMigrations(): static
    {
        static::$runsMigrations = false;

        return new static;
    }

    /**
     * Configure Cashier to not register its routes.
     */
    public static function ignoreRoutes(): static
    {
        static::$registersRoutes = false;

        return new static;
    }

    /**
     * Set the subscription model class name.
     */
    public static function useSubscriptionModel(string $subscriptionModel): void
    {
        static::$subscriptionModel = $subscriptionModel;
    }

    /**
     * Set the customer (billable) model class name.
     */
    public static function useCustomerModel(string $customerModel): void
    {
        static::$customerModel = $customerModel;
    }

    /**
     * Find a billable model instance by its Bachs customer ID.
     */
    public static function findBillable(?string $customerId): ?Model
    {
        if (is_null($customerId)) {
            return null;
        }

        return (new static::$customerModel)->where('bachs_id', $customerId)->first();
    }

    /**
     * Get a new instance of the subscription model.
     */
    public static function subscription(array $attributes = []): Model
    {
        return new static::$subscriptionModel($attributes);
    }
}
