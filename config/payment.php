<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway that will be used to
    | process payments. You may set this to any of the connections defined
    | in the "gateways" array below.
    |
    */
    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'zarinpal'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Here you may configure the payment gateways for your application.
    |
    */
    'gateways' => [
        'zarinpal' => [
            'driver' => 'zarinpal',
            'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
            'sandbox' => env('ZARINPAL_SANDBOX', false),
            'callback_url' => env('ZARINPAL_CALLBACK_URL'),
        ],
        'mellat' => [
            'driver' => 'mellat',
            'terminal_id' => env('MELLAT_TERMINAL_ID'),
            'username' => env('MELLAT_USERNAME'),
            'password' => env('MELLAT_PASSWORD'),
            'callback_url' => env('MELLAT_CALLBACK_URL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Tables
    |--------------------------------------------------------------------------
    |
    | You can define the table names used by the package here.
    |
    */
    'tables' => [
        'banks' => 'banks',
        'gateways' => 'payment_gateways',
        'transactions' => 'payment_transactions',
    ],
];
