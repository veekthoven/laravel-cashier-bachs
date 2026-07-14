# Laravel Cashier (Bachs)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/veekthoven/laravel-cashier-bachs.svg?style=flat-square)](https://packagist.org/packages/veekthoven/laravel-cashier-bachs)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/veekthoven/laravel-cashier-bachs/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/veekthoven/laravel-cashier-bachs/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/veekthoven/laravel-cashier-bachs/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/veekthoven/laravel-cashier-bachs/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/veekthoven/laravel-cashier-bachs.svg?style=flat-square)](https://packagist.org/packages/veekthoven/laravel-cashier-bachs)

Laravel Cashier Bachs provides an expressive, fluent interface to [Bachs'](https://bachs.io) subscription billing services — modeled after [Laravel Cashier for Stripe](https://github.com/laravel/cashier-stripe). It handles Bachs customers, hosted checkouts, one-time payments, subscriptions with trials, plan swaps with proration, cancellation grace periods, and webhook-driven state syncing.

> [!NOTE]
> In Bachs, subscriptions are always started by the customer completing a **hosted checkout** for a recurring product. This package creates the checkout session and then keeps your local subscription records in sync via webhooks.

## Installation

Install the package via composer:

```bash
composer require veekthoven/laravel-cashier-bachs
```

Run Cashier's migrations, which add a `bachs_id` and `trial_ends_at` column to your `users` table and create the `subscriptions` table:

```bash
php artisan migrate
```

You may publish the migrations to customize them:

```bash
php artisan vendor:publish --tag="cashier-migrations"
```

And optionally the config file:

```bash
php artisan vendor:publish --tag="cashier-config"
```

### Configuration

Add your Bachs credentials to your `.env` file:

```dotenv
BACHS_API_KEY=sk_sandbox_...
BACHS_WEBHOOK_SECRET=whsec_...
```

Keys prefixed with `sk_sandbox_` are automatically routed to the Bachs sandbox API and `sk_live_` keys to production — no extra configuration needed.

### Billable model

Add the `Billable` trait to your model:

```php
use Veekthoven\CashierBachs\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

If your billable model is not `App\Models\User`, register it in a service provider:

```php
use Veekthoven\CashierBachs\Cashier;

Cashier::useCustomerModel(Team::class);
```

## Customers

```php
// Create the customer in Bachs (uses the model's email, name and phone)...
$user->createAsBachsCustomer();

// Or with overrides...
$user->createAsBachsCustomer(['name' => 'Jane Doe']);

$user->hasBachsId();
$user->bachsId();

// Retrieve the raw Bachs customer payload...
$customer = $user->asBachsCustomer();

// Push local changes to Bachs...
$user->syncBachsCustomerDetails();
```

The trait reads `email`, `name` and `phone` from your model by default. Override `bachsEmail()`, `bachsName()` or `bachsPhone()` to customize.

## Subscriptions

### Creating subscriptions

Subscriptions start with a hosted checkout for a recurring Bachs product:

```php
use Illuminate\Http\Request;

Route::get('/subscribe', function (Request $request) {
    return $request->user()
        ->newSubscription('default', 'prod_abc123')
        ->checkout([
            'success_url' => route('billing.success'),
            'cancel_url' => route('billing.cancel'),
        ]);
});
```

The `Checkout` object is responsable — returning it redirects the customer to the Bachs-hosted checkout page. You can also grab the URL yourself with `$checkout->url()`.

Once the customer pays, Bachs sends a `customer.subscription.created` webhook and Cashier creates the local subscription record automatically. The subscription "type" (`default` above) travels through the checkout session's metadata.

### Checking subscription status

```php
$user->subscribed();                          // has a valid "default" subscription
$user->subscribed('default', 'prod_abc123');  // ...to a specific product
$user->subscribedToProduct('prod_abc123');

$subscription = $user->subscription();

$subscription->valid();          // active, trialing, past_due, or on grace period
$subscription->active();
$subscription->onTrial();
$subscription->pastDue();
$subscription->paused();
$subscription->canceled();
$subscription->onGracePeriod();  // canceled but still within the paid period
$subscription->ended();
```

### Trials

Recurring products configured with a trial in Bachs start subscriptions in the `trialing` state — no extra code needed. You can also manage trials directly:

```php
$subscription->extendTrial(now()->addDays(30));
$subscription->endTrial(); // ends the trial and bills immediately
```

For trials **without** requiring a checkout ("generic" trials), set `trial_ends_at` on the billable model when creating it:

```php
$user = User::create([
    // ...
    'trial_ends_at' => now()->addDays(14),
]);

$user->onTrial();        // true (generic or subscription trial)
$user->onGenericTrial(); // true (generic trial only)
```

### Swapping plans

```php
$subscription->swap('prod_premium');                 // prorated, invoiced now (Bachs default)
$subscription->swapWithoutProration('prod_premium'); // no proration
$subscription->swap('prod_premium', [
    'proration_behavior' => 'next_cycle',            // settle the difference next cycle
]);
```

### Cancelling

```php
$subscription->cancel();                  // at period end; enters a grace period
$subscription->cancelNow();               // immediately
$subscription->cancel('Too expensive');   // with a reason
```

While on the grace period, `$subscription->valid()` remains `true` until the paid period runs out.

### Payment method

```php
$subscription->updatePaymentMethod('pm_abc123');
```

## One-time payments

Sell one-time products through a hosted checkout:

```php
// Single product...
return $user->checkout('prod_ebook', [
    'success_url' => route('shop.success'),
]);

// Multiple products with quantities...
return $user->checkout([
    ['product_id' => 'prod_course', 'quantity' => 2],
    'prod_ebook',
]);
```

### Refunds

```php
$user->refund('chr_1a2b3c4d5e6f');
$user->refund('chr_1a2b3c4d5e6f', ['amount' => '10.00', 'reason' => 'Requested by customer']);
```

## Webhooks

Cashier registers a webhook route at `/bachs/webhook` that keeps subscriptions in sync (`customer.subscription.created`, `.updated`, `.deleted`). Create the Bachs webhook endpoint with:

```bash
php artisan cashier:webhook
```

The command prints the signing secret — add it to your `.env` as `BACHS_WEBHOOK_SECRET`. Incoming webhooks are verified against the `X-Bachs-Signature` header (HMAC-SHA256) and stale deliveries are rejected.

> [!IMPORTANT]
> Make sure the webhook route is excluded from CSRF verification. In `bootstrap/app.php`:
>
> ```php
> ->withMiddleware(function (Middleware $middleware) {
>     $middleware->validateCsrfTokens(except: ['bachs/*']);
> })
> ```

### Handling other events

Listen for any Bachs event via the `WebhookReceived` event:

```php
use Veekthoven\CashierBachs\Events\WebhookReceived;

Event::listen(function (WebhookReceived $event) {
    if ($event->payload['type'] === 'invoice.payment_failed') {
        // ...
    }
});
```

A `WebhookHandled` event fires after Cashier has processed one of the events it manages itself.

## Low-level API access

Anything not covered by the fluent interface is available through the API client:

```php
use Veekthoven\CashierBachs\Cashier;

$products = Cashier::api()->listProducts();
$payment = Cashier::api()->getPayment('pay_1a2b3c4d5e');
$response = Cashier::api()->get('/accounts/balances');
```

Failed requests throw `Veekthoven\CashierBachs\Exceptions\BachsApiError`, exposing the stable `errorCode`, validation `errors`, and the `requestId` for support.

## Customization

```php
use Veekthoven\CashierBachs\Cashier;

Cashier::ignoreRoutes();      // don't register the webhook route
Cashier::ignoreMigrations();  // don't load the package migrations
Cashier::useSubscriptionModel(CustomSubscription::class);
Cashier::formatCurrencyUsing(fn ($amount, $currency) => /* ... */);
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Victor Abbah Nkoms](https://github.com/veekthoven)
- Inspired by [Laravel Cashier (Stripe)](https://github.com/laravel/cashier-stripe)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
