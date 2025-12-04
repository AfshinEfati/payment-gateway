<?php

namespace App\Payment;

use Illuminate\Contracts\Foundation\Application;
use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;

class PaymentManager
{
    protected Application $app;
    protected ?string $driverName = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function driver(string $name): self
    {
        $this->driverName = $name;
        return $this;
    }

    /**
     * @throws PaymentException
     */
    public function initialize(PaymentRequestDTO $dto): array
    {
        $driver = $this->driverName ?: $this->getDefaultGateway();
        $gateway = $this->gateway($driver);

        $result = $gateway->initialize($dto);

        $this->createTransaction($driver, $dto, $result);

        return $result;
    }

    /**
     * @throws PaymentException
     */
    public function verify(PaymentVerifyDTO $dto): array
    {
        $driver = $dto->gateway ?: ($this->driverName ?: $this->getDefaultGateway());
        $gateway = $this->gateway($driver);

        try {
            $result = $gateway->verify($dto);
            $this->updateTransaction($dto, $result, 'success');

            return $result;
        } catch (PaymentException $e) {
            $this->updateTransaction($dto, ['error' => $e->getMessage()], 'failed');
            throw $e;
        }
    }

    protected function createTransaction(string $driver, PaymentRequestDTO $dto, array $result): void
    {
        $transactionModel = $this->app['config']['payment.models.transaction'];
        $gatewayModel = $this->app['config']['payment.models.gateway'];

        $gateway = $gatewayModel::where('driver', $driver)->first();

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

    protected function updateTransaction(PaymentVerifyDTO $dto, array $result, string $status): void
    {
        $transactionModel = $this->app['config']['payment.models.transaction'];

        $query = $transactionModel::query();

        $transaction = $query->where('order_id', $dto->metadata['order_id'] ?? null)
            ->orWhere('order_id', $dto->authority)
            ->latest()
            ->first();

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
     * @throws PaymentException
     */
    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: $this->getDefaultDriver(); // Changed getDefaultGateway to getDefaultDriver
        $this->driverName = $name;

        // Check for custom bindings
        $binding = "payment.$name";
        if ($this->app->bound($binding)) {
            return $this->app->make($binding);
        }

        throw new PaymentException("Gateway [$name] is not supported or not registered.");
    }

    public function getDefaultDriver()
    {
        return $this->app['config']['payment.default'] ?? 'zarinpal';
    }

    public function getDriverName(): ?string
    {
        return $this->driverName;
    }

    public function hasDriver(string $method): bool
    {
        // This method was added from the provided snippet.
        // The original class does not have customCreators or createXDriver methods.
        // We'll adapt it to check for bound services or configured gateways.
        if ($this->app->bound("payment.$method")) {
            return true;
        }

        if (config("payment.gateways.$method")) {
            return true;
        }

        return false;
    }

    public function __call($method, $parameters)
    {
        if (config("payment.gateways.{$method}")) {
            return $this->driver($method);
        }

        // The original method called $this->gateway()->$method(...$parameters);
        // The new structure implies that if it's not a configured gateway,
        // it might be a method on the default gateway.
        // We'll keep the original behavior for non-driver calls.
        return $this->gateway()->$method(...$parameters);
    }
}
