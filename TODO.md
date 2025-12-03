# Laravel Payment Gateway Package – TODO Checklist

This document outlines the complete TODO list for building a reusable, multi-gateway Laravel payment package. The package will support multiple payment providers, initialization and verification flows, shared configuration, database structure, seeders, and gateway adapters.

---

## 1. Package Structure Setup

- [x] Add base files:
  - [x] `composer.json`
  - [x] `src/`
  - [x] `src/PaymentServiceProvider.php`
  - [x] `config/payment.php`
  - [x] `database/migrations/`
  - [x] `database/seeders/`
  - [ ] `routes/payment.php` (optional)

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

- [x] `Contracts/PaymentGatewayInterface.php`
- [x] `Gateways/ProviderName.php` (e.g. Zarinpal, Mellat, IdPay, etc.)
- [x] `DTOs/PaymentRequestDTO.php`
- [x] `DTOs/PaymentVerifyDTO.php`
- [x] `Exceptions/PaymentException.php`
- [x] `Managers/PaymentManager.php`

---

## 3. Define Gateway Interface

The `PaymentGatewayInterface` must define:

- `initialize(PaymentRequestDTO $dto): PaymentInitResult`
- `verify(PaymentVerifyDTO $dto): PaymentVerifyResult`
- `getTransactionId(): string|null`
- `getTrackingCode(): string|null`
- `getRawResponse(): array|null`

All gateway adapters must implement this interface. [x]

---

## 4. Implement PaymentManager

- [x] Read default gateway from config
- [x] Allow dynamic gateway selection (`setGateway('zarinpal')`)
- [x] Resolve gateway drivers via container bindings
- [x] Execute initialization and verification operations
- [x] Normalize exceptions to `PaymentException`

---

## 5. Create `config/payment.php`

- [x] Default gateway configuration
- [x] Define each provider:
  ```
  'zarinpal' => [
      'merchant_id' => env('ZARINPAL_MID'),
      'callback' => '/payment/zarinpal/callback',
      'sandbox' => false,
  ],
  ```
- [x] Global settings (timeout, logging, retry, etc.)

---

## 6. Database Structure

### Table: `banks` [x]

- id
- name_en
- name_fa
- code
- logo_url

### Table: `payment_gateways` [x]

- id
- bank_id (FK)
- name_en
- name_fa
- driver (class reference)
- is_active
- config (json)

### Table: `payment_transactions` [x]

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

### Bank Seeder [x]

Populate all major Iranian banks:

- Mellat
- Saman
- Pasargad
- Parsian
- Ayandeh
- EN Bank
- Melli
- Others

### Gateway Seeder [x]

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

- [x] Create gateway class
- [x] Map provider request input fields
- [x] Implement `initialize()` → returns payment URL or redirect token
- [x] Implement `verify()` → returns status, refId, trackingCode
- [x] Handle provider-specific error codes
- [x] Normalize error handling into `PaymentException`

---

## 9. Service Provider

- [x] Register package config via `mergeConfigFrom`
- [x] Publish:
  - [x] config
  - [x] migrations
  - [x] seeders
- [x] Bind all gateway drivers:
  ```
  $this->app->bind('payment.zarinpal', Zarinpal::class);
  ```

---

## 10. (Optional) Facade

Supports: [x]

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
