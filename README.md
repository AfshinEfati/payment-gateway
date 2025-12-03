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

Run the installation command to publish the config, migrations, seeders, models, and core logic:

```bash
php artisan payment:install
```

This command will:

1. Publish configuration to `config/payment.php`
2. Publish migrations to `database/migrations`
3. Publish seeders to `database/seeders`
4. Publish models to `app/Models`
5. **Scaffold Core Logic**: Copy Contracts, Gateways, DTOs, and Managers to `app/Payment/`

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

The core logic is now located in `app/Payment`. You can customize any part of it.

Use the `Payment` facade to initialize a payment:

```php
use App\Payment\Managers\PaymentManager; // Or use Facade
use PaymentGateway\Facades\Payment;
use App\Payment\DTOs\PaymentRequestDTO;

$dto = new PaymentRequestDTO(
    amount: 10000,
    orderId: 'ORD-123',
    callbackUrl: route('payment.callback'),
    description: 'Order payment',
    mobile: '09123456789'
);

$result = Payment::initialize($dto);
```

### Dynamic Gateway Selection

```php
// Use Zarinpal
Payment::zarinpal()->initialize($dto);
```

### Customization

Since the code is in `app/Payment`, you can directly edit:

- `app/Payment/Gateways/Zarinpal.php`
- `app/Payment/Managers/PaymentManager.php`
- `app/Payment/DTOs/PaymentRequestDTO.php`

The package automatically detects if these files exist in `app/Payment` and uses them instead of the package defaults.

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
