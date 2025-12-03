# Laravel Payment Gateway Package – TODO Checklist

This document outlines the complete TODO list for building a reusable, multi-gateway Laravel payment package. The package will support multiple payment providers, initialization and verification flows, shared configuration, database structure, seeders, and gateway adapters.

---

## 1. Package Structure Setup

- Add base files:
  - `composer.json`
  - `src/`
  - `src/PaymentServiceProvider.php`
  - `config/payment.php`
  - `database/migrations/`
  - `database/seeders/`
  - `routes/payment.php` (optional)

---

## 2. Define Package Folder Architecture
```
src/
  Contracts/
  DTOs/
  Gateways/
  Exceptions/
  Managers/
  Helpers/
config/
database/
  migrations/
  seeders/
```

- `Contracts/PaymentGatewayInterface.php`
- `Gateways/ProviderName.php` (e.g. Zarinpal, Mellat, IdPay, etc.)
- `DTOs/PaymentRequestDTO.php`
- `DTOs/PaymentVerifyDTO.php`
- `Exceptions/PaymentException.php`
- `Managers/PaymentManager.php`

---

## 3. Define Gateway Interface
The `PaymentGatewayInterface` must define:

- `initialize(PaymentRequestDTO $dto): PaymentInitResult`
- `verify(PaymentVerifyDTO $dto): PaymentVerifyResult`
- `getTransactionId(): string|null`
- `getTrackingCode(): string|null`
- `getRawResponse(): array|null`

All gateway adapters must implement this interface.

---

## 4. Implement PaymentManager
- Read default gateway from config
- Allow dynamic gateway selection (`setGateway('zarinpal')`)
- Resolve gateway drivers via container bindings
- Execute initialization and verification operations
- Normalize exceptions to `PaymentException`

---

## 5. Create `config/payment.php`
- Default gateway configuration
- Define each provider:
  ```
  'zarinpal' => [
      'merchant_id' => env('ZARINPAL_MID'),
      'callback' => '/payment/zarinpal/callback',
      'sandbox' => false,
  ],
  ```
- Global settings (timeout, logging, retry, etc.)

---

## 6. Database Structure
### Table: `banks`
- id
- name_en
- name_fa
- code
- logo_url

### Table: `payment_gateways`
- id
- bank_id (FK)
- name_en
- name_fa
- driver (class reference)
- is_active
- config (json)

### Table: `payment_transactions`
- id
- gateway_id
- order_id
- amount
- status
- ref_id
- tracking_code
- request_payload (json)
- response_payload (json)
- verified_at
- timestamps

---

## 7. Seeders
### Bank Seeder
Populate all major Iranian banks:
- Mellat
- Saman
- Pasargad
- Parsian
- Ayandeh
- EN Bank
- Melli
- Others

### Gateway Seeder
- Zarinpal
- IdPay
- NextPay
- Mellat IPG
- Saman SEP
- Parsian PG
- Others as needed

---

## 8. Implement Gateway Adapters
For each provider:
- Create gateway class
- Map provider request input fields
- Implement `initialize()` → returns payment URL or redirect token
- Implement `verify()` → returns status, refId, trackingCode
- Handle provider-specific error codes
- Normalize error handling into `PaymentException`

---

## 9. Service Provider
- Register package config via `mergeConfigFrom`
- Publish:
  - config
  - migrations
  - seeders
- Bind all gateway drivers:
  ```
  $this->app->bind('payment.zarinpal', Zarinpal::class);
  ```

---

## 10. (Optional) Facade
Supports:
```php
Payment::gateway('zarinpal')->initialize($dto);
```

---

## 11. Testing (Optional)
- Mock provider initialization
- Test verification flow
- Test failure scenarios
- Test config loading
- Test manager routing

---

## 12. Usage in External Projects
After publishing and installing:

### Install:
```
composer require vendor/payment-gateway
```

### Publish:
```
php artisan vendor:publish --tag=payment-config
php artisan vendor:publish --tag=payment-migrations
```

### Migrate:
```
php artisan migrate
```

### Seed:
```
php artisan db:seed --class=PaymentGatewaySeeder
```

### Example usage:
```php
$result = Payment::gateway('zarinpal')->initialize(
    new PaymentRequestDTO(
        amount: 120000,
        orderId: 'ORD-1001',
        callback: route('payment.callback')
    )
);
```

---

## Completion
This checklist describes every step required to build a reusable, clean, maintainable, production-ready Laravel payment package.
