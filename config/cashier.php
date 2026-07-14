<?php

// config for Veekthoven/CashierBachs
return [

    /*
    |--------------------------------------------------------------------------
    | Bachs API Key
    |--------------------------------------------------------------------------
    |
    | The Bachs secret key used to authenticate API requests. Keys prefixed
    | with "sk_sandbox_" are routed to the sandbox deployment while keys
    | prefixed with "sk_live_" are routed to the production deployment.
    |
    */

    'api_key' => env('BACHS_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Bachs API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for Bachs API requests. When left null, the URL will be
    | resolved automatically from the API key prefix: sandbox keys go to
    | the sandbox API and live keys go to the production API.
    |
    */

    'api_url' => env('BACHS_API_URL'),

    /*
    |--------------------------------------------------------------------------
    | Bachs Webhooks
    |--------------------------------------------------------------------------
    |
    | Your Bachs webhook signing secret, used to verify that incoming webhook
    | requests were genuinely sent by Bachs. You may also set the tolerance,
    | in seconds, for rejecting stale webhook deliveries.
    |
    */

    'webhook' => [
        'secret' => env('BACHS_WEBHOOK_SECRET'),
        'tolerance' => env('BACHS_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cashier Path
    |--------------------------------------------------------------------------
    |
    | This is the base URI path where Cashier's views, such as the webhook
    | route, will be available from. You're free to tweak this path based
    | on the needs of your particular application or design preferences.
    |
    */

    'path' => env('CASHIER_PATH', 'bachs'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating charges
    | from your application. Of course, you are welcome to use any of the
    | various currencies that are currently supported via Bachs.
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'USD'),

];
