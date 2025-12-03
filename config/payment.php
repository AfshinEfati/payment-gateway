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
        'idpay' => [
            'driver' => 'idpay',
            'api_key' => env('IDPAY_API_KEY'),
            'sandbox' => env('IDPAY_SANDBOX', false),
            'callback_url' => env('IDPAY_CALLBACK_URL'),
        ],
        'irankish' => [
            'driver' => 'irankish',
            'terminal_id' => env('IRANKISH_TERMINAL_ID'),
            'password' => env('IRANKISH_PASSWORD'),
            'callback_url' => env('IRANKISH_CALLBACK_URL'),
        ],
        'nextpay' => [
            'driver' => 'nextpay',
            'api_key' => env('NEXTPAY_API_KEY'),
            'callback_url' => env('NEXTPAY_CALLBACK_URL'),
        ],
        'payir' => [
            'driver' => 'payir',
            'api_key' => env('PAYIR_API_KEY'),
            'callback_url' => env('PAYIR_CALLBACK_URL'),
        ],
        'parsian' => [
            'driver' => 'parsian',
            'pin' => env('PARSIAN_PIN'),
            'callback_url' => env('PARSIAN_CALLBACK_URL'),
        ],
        'pasargad' => [
            'driver' => 'pasargad',
            'terminal_id' => env('PASARGAD_TERMINAL_ID'),
            'merchant_id' => env('PASARGAD_MERCHANT_ID'),
            'private_key' => env('PASARGAD_PRIVATE_KEY'), // Path or content
            'callback_url' => env('PASARGAD_CALLBACK_URL'),
        ],
        'payping' => [
            'driver' => 'payping',
            'api_key' => env('PAYPING_API_KEY'),
            'callback_url' => env('PAYPING_CALLBACK_URL'),
        ],
        'sadad' => [
            'driver' => 'sadad',
            'merchant_id' => env('SADAD_MERCHANT_ID'),
            'terminal_id' => env('SADAD_TERMINAL_ID'),
            'merchant_key' => env('SADAD_MERCHANT_KEY'),
            'application_name' => env('SADAD_APPLICATION_NAME', 'Payment'),
            'callback_url' => env('SADAD_CALLBACK_URL'),
        ],
        'saman' => [
            'driver' => 'saman',
            'terminal_id' => env('SAMAN_TERMINAL_ID'),
            'merchant_id' => env('SAMAN_MERCHANT_ID'),
            'callback_url' => env('SAMAN_CALLBACK_URL'),
        ],
        'asanpardakht' => [
            'driver' => 'asanpardakht',
            'merchant_config_id' => env('ASANPARDAKHT_MERCHANT_CONFIG_ID'),
            'username' => env('ASANPARDAKHT_USERNAME'),
            'password' => env('ASANPARDAKHT_PASSWORD'),
            'key' => env('ASANPARDAKHT_KEY'),
            'iv' => env('ASANPARDAKHT_IV'),
            'callback_url' => env('ASANPARDAKHT_CALLBACK_URL'),
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

    /*
    |--------------------------------------------------------------------------
    | Payment Models
    |--------------------------------------------------------------------------
    |
    | You can define the model classes used by the package here.
    |
    */
    'models' => [
        'bank' => \PaymentGateway\Models\Bank::class,
        'gateway' => \PaymentGateway\Models\PaymentGateway::class,
        'transaction' => \PaymentGateway\Models\PaymentTransaction::class,
    ],
];
