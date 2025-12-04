<?php

namespace App\Payment\Gateways;

use Illuminate\Support\Facades\Http;
use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;
use App\Payment\PaymentException;
use Illuminate\Http\Client\ConnectionException;

class IDPay implements PaymentGatewayInterface
{
    protected array $config;
    protected ?array $rawResponse = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @throws PaymentException
     * @throws ConnectionException
     */
    public function initialize(PaymentRequestDTO $dto): array
    {
        $url = 'https://api.idpay.ir/v1.1/payment';

        $response = Http::withHeaders([
            'X-API-KEY' => $this->config['api_key'],
            'X-SANDBOX' => $this->config['sandbox'] ? '1' : '0',
        ])->post($url, [
            'order_id' => $dto->orderId,
            'amount' => $dto->amount,
            'callback' => $dto->callbackUrl,
            'name' => $dto->metadata['name'] ?? null,
            'phone' => $dto->mobile,
            'mail' => $dto->email,
            'desc' => $dto->description,
        ]);

        $this->rawResponse = $response->json();

        if ($response->failed() || !isset($this->rawResponse['id'])) {
            throw new PaymentException("IDPay initialization failed: " . ($this->rawResponse['error_message'] ?? $response->body()));
        }

        return [
            'url' => $this->rawResponse['link'],
            'token' => $this->rawResponse['id'],
        ];
    }

    /**
     * @throws PaymentException
     * @throws ConnectionException
     */
    public function verify(PaymentVerifyDTO $dto): array
    {
        $url = 'https://api.idpay.ir/v1.1/payment/verify';

        $response = Http::withHeaders([
            'X-API-KEY' => $this->config['api_key'],
            'X-SANDBOX' => $this->config['sandbox'] ? '1' : '0',
        ])->post($url, [
            'id' => $dto->authority,
            'order_id' => $dto->metadata['order_id'] ?? null,
        ]);

        $this->rawResponse = $response->json();

        if ($response->failed() || !isset($this->rawResponse['status']) || $this->rawResponse['status'] != 100) {
            throw new PaymentException("IDPay verification failed: " . ($this->rawResponse['error_message'] ?? 'Unknown error'));
        }

        return [
            'status' => 'success',
            'ref_id' => $this->rawResponse['track_id'],
            'tracking_code' => $this->rawResponse['payment']['track_id'] ?? $this->rawResponse['track_id'],
        ];
    }

    public function getTransactionId(): ?string
    {
        return $this->rawResponse['id'] ?? null;
    }

    public function getTrackingCode(): ?string
    {
        return $this->rawResponse['track_id'] ?? null;
    }

    public function getRawResponse(): ?array
    {
        return $this->rawResponse;
    }
}
