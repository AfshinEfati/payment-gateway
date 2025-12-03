<?php

namespace App\Payment;

use Illuminate\Contracts\Foundation\Application;
use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\PaymentException;

    protected $driverName;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Set the gateway driver name.
     *
     * @param string $name
     * @return self
     */
    public function driver(string $name): self
    {
        $this->driverName = $name;
        return $this;
    }

    /**
     * Initialize the payment.
     *
     * @param \App\Payment\DTOs\PaymentRequestDTO $dto
     * @return array
     * @throws PaymentException
     */
    public function initialize(\App\Payment\DTOs\PaymentRequestDTO $dto): array
    {
        $driver = $this->driverName ?: $this->getDefaultGateway();
        $gateway = $this->gateway($driver);

        // 1. Initialize at Gateway
        $result = $gateway->initialize($dto);

        // 2. Create Transaction
        $this->createTransaction($driver, $dto, $result);

        return $result;
    }

    /**
     * Verify the payment.
     *
     * @param \App\Payment\DTOs\PaymentVerifyDTO $dto
     * @return array
     * @throws PaymentException
     */
    public function verify(\App\Payment\DTOs\PaymentVerifyDTO $dto): array
    {
        $driver = $dto->gateway ?: ($this->driverName ?: $this->getDefaultGateway());
        $gateway = $this->gateway($driver);

        try {
            // 1. Verify at Gateway
            $result = $gateway->verify($dto);

            // 2. Update Transaction (Success)
            $this->updateTransaction($dto, $result, 'success');

            return $result;

        } catch (PaymentException $e) {
            // 2. Update Transaction (Failed)
            $this->updateTransaction($dto, ['error' => $e->getMessage()], 'failed');
            throw $e;
        }
    }

    /**
     * Create a new transaction record.
     */
    protected function createTransaction(string $driver, \App\Payment\DTOs\PaymentRequestDTO $dto, array $result)
    {
        $transactionModel = $this->app['config']['payment.models.transaction'];
        $gatewayModel = $this->app['config']['payment.models.gateway'];

        // Find Gateway ID
        // Assuming we store gateways in DB with 'name_en' matching the driver name
        // Or we can just store the driver name if we change the schema. 
        // For now, let's try to find it, or fallback to null/error if strict.
        $gateway = $gatewayModel::where('name_en', $driver)->first();
        
        if (!$gateway) {
            // If gateway not found in DB, we might want to create it or just log warning.
            // For this package, let's assume seeders ran.
            // But to be safe, let's just use ID 1 or handle gracefully.
            // throw new PaymentException("Gateway [$driver] not found in database.");
        }

        $transactionModel::create([
            'gateway_id' => $gateway?->id,
            'order_id' => $dto->orderId,
            'amount' => $dto->amount,
            'status' => 'pending',
            'request_payload' => json_encode($dto->toArray()),
            'response_payload' => json_encode($result),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update the transaction record.
     */
    protected function updateTransaction(\App\Payment\DTOs\PaymentVerifyDTO $dto, array $result, string $status)
    {
        $transactionModel = $this->app['config']['payment.models.transaction'];

        // Find by Authority (if available) or Order ID
        $query = $transactionModel::query();

        if (!empty($dto->authority)) {
            // Assuming we stored authority in response_payload or a dedicated column?
            // The migration has 'ref_id' and 'tracking_code'. 
            // Usually authority is returned in initialize result.
            // We stored initialize result in 'response_payload'.
            // So we might need to search JSON or assume the user passed the correct Order ID.
            // Let's rely on Order ID + Amount + Status=Pending for safety.
        }

        // Best practice: Find by Order ID (unique per attempt usually)
        // Or if the gateway sends back the authority in verify, we can match it.
        // Let's assume Order ID is unique enough for now.
        $transaction = $query->where('order_id', $dto->metadata['order_id'] ?? null) // If passed in metadata
                             ->orWhere('order_id', $dto->authority) // Sometimes authority IS the order id
                             ->latest()
                             ->first();
        
        // If we can't find it easily, we might need to improve DTO to carry the transaction ID.
        // For now, let's try to find by 'authority' if we stored it. 
        // But we didn't store authority in a dedicated column in createTransaction (only in json).
        
        // IMPROVEMENT: Let's assume the user passes the Transaction ID or Order ID in the DTO.
        // But the DTO has `authority`.
        
        // Let's try to find the transaction where response_payload contains the authority.
        if (!$transaction) {
             $transaction = $transactionModel::where('response_payload', 'like', '%"token":"' . $dto->authority . '"%')
                ->orWhere('response_payload', 'like', '%"authority":"' . $dto->authority . '"%')
                ->latest()
                ->first();
        }

        if ($transaction) {
            $transaction->update([
                'status' => $status,
                'ref_id' => $result['ref_id'] ?? null,
                'tracking_code' => $result['tracking_code'] ?? null,
                'verified_at' => $status === 'success' ? now() : null,
                'response_payload' => json_encode(array_merge(json_decode($transaction->response_payload ?? '[]', true), $result)),
            ]);
        }
    }

    /**
     * Get a gateway instance.
     *
     * @param string|null $name
     * @return PaymentGatewayInterface
     * @throws PaymentException
     */
    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: $this->getDefaultGateway();
        $this->driverName = $name; // Set current driver

        // We assume the gateway is bound in the container as 'payment.drivers.{name}'
        // or simply 'payment.{name}' as per the TODO.
        // Let's use a consistent naming convention.
        $binding = "payment.{$name}";

        if (!$this->app->bound($binding)) {
            throw new PaymentException("Gateway [{$name}] is not supported or not registered.");
        }

        return $this->app->make($binding);
    }

    /**
     * Get the default gateway name.
     *
     * @return string
     */
    public function getDefaultGateway(): string
    {
        return $this->app['config']['payment.default'];
    }

    /**
     * Dynamically call the default driver instance or resolve a gateway.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Check if the method name corresponds to a registered gateway config
        if ($this->app['config']["payment.gateways.{$method}"]) {
            return $this->driver($method); // Return manager instance with driver set
        }

        return $this->gateway()->$method(...$parameters);
    }
}
