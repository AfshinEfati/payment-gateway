# Laravel Payment Gateway

A reusable, multi-gateway Laravel payment package supporting Iranian banking system.

## Features

- **Multiple Gateways**: Support for Zarinpal, Mellat, and more.
- **Dynamic Access**: Access specific gateways easily via Facade (e.g., `Payment::zarinpal()`).
- **Customizable Models**: Publish and customize Bank, Gateway, and Transaction models.
- **Database Support**: Includes migrations and seeders for banks and gateways.
- **DTO Support**: Uses Data Transfer Objects for consistent request/response handling.

## Requirements

- PHP ^8.2
- Laravel ^11.0 | ^12.0

## Installation

Install the package via composer:

```bash
composer require efati/payment-gateway
```

## Configuration

Publish the configuration file, migrations, and seeders:

```bash
# Publish Config
php artisan vendor:publish --tag=efati-payment-config

# Publish Migrations
php artisan vendor:publish --tag=efati-payment-migrations

# Publish Seeders
php artisan vendor:publish --tag=efati-payment-seeders
```

Run migrations:

```bash
php artisan migrate
```

Seed the database with banks and gateways:

```bash
php artisan db:seed --class=PaymentGateway\Database\Seeders\BankSeeder
php artisan db:seed --class=PaymentGateway\Database\Seeders\GatewaySeeder
```

## Usage

### Basic Usage

Use the `Payment` facade to initialize a payment with the default gateway (defined in `config/payment.php`):

```php
use PaymentGateway\Facades\Payment;
use PaymentGateway\DTOs\PaymentRequestDTO;

$dto = new PaymentRequestDTO(
    amount: 10000, // Amount in Rials (Integer)
    orderId: 'ORD-123',
    callbackUrl: route('payment.callback'),
    description: 'Order payment',
    mobile: '09123456789'
);

$result = Payment::initialize($dto);

// Redirect user to bank
return redirect($result['url']);
```

### Dynamic Gateway Selection

You can choose a specific gateway at runtime:

```php
// Use Zarinpal
Payment::zarinpal()->initialize($dto);

// Use Mellat
Payment::mellat()->initialize($dto);

// Use by name string
Payment::gateway('zarinpal')->initialize($dto);
```

### Verification

On your callback route:

```php
use PaymentGateway\Facades\Payment;
use PaymentGateway\DTOs\PaymentVerifyDTO;

$dto = new PaymentVerifyDTO(
    amount: 10000,
    authority: request('Authority'), // or whatever the gateway sends
    gateway: 'zarinpal'
);

try {
    $receipt = Payment::verify($dto);
    // $receipt['ref_id'], $receipt['tracking_code']

    return "Payment Successful! Ref ID: " . $receipt['ref_id'];
} catch (\PaymentGateway\Exceptions\PaymentException $e) {
    return "Payment Failed: " . $e->getMessage();
}
```

## Customizing Models

If you need to extend or modify the models (e.g., to add relationships), you can publish them to your `app/Models` directory:

```bash
php artisan vendor:publish --tag=efati-payment-models
```

This will copy `Bank`, `PaymentGateway`, and `PaymentTransaction` models to `app/Models`. The package will automatically use your published models if you update the `config/payment.php` file:

```php
// config/payment.php
'models' => [
    'bank' => \App\Models\Bank::class,
    'gateway' => \App\Models\PaymentGateway::class,
    'transaction' => \App\Models\PaymentTransaction::class,
],
```

## License

The MIT License (MIT).
