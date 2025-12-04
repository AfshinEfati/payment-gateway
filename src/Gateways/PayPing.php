<?php

namespace PaymentGateway\Gateways;

use Illuminate\Support\Facades\Http;
use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\DTOs\PaymentRequestDTO;
use PaymentGateway\DTOs\PaymentVerifyDTO;
use PaymentGateway\Exceptions\PaymentException;

class PayPing implements PaymentGatewayInterface
{
    protected $config;
    protected $rawResponse;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(PaymentRequestDTO $dto): array
    {
        $url = 'https://api.payping.ir/v2/pay';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json',
        ])->post($url, [
            'payerIdentity' => $dto->mobile,
            'amount' => $dto->amount,
            'payerName' => $dto->metadata['name'] ?? null,
            'description' => $dto->description,
            'returnUrl' => $dto->callbackUrl,
            'clientRefId' => $dto->orderId,
        ]);

        $this->rawResponse = $response->json();

        if ($response->failed() || !isset($this->rawResponse['code'])) {
            throw new PaymentException("PayPing initialization failed: " . ($response->body()));
        }

        $code = $this->rawResponse['code'];

        return [
            'url' => "https://api.payping.ir/v2/pay/gotoipg/$code",
            'token' => $code,
        ];
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        $url = 'https://api.payping.ir/v2/pay/verify';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json',
        ])->post($url, [
            'refId' => $dto->authority,
            'amount' => $dto->amount,
        ]);

        $this->rawResponse = $response->json();

        if ($response->failed() || !isset($this->rawResponse['cardNumber'])) {
            throw new PaymentException("PayPing verification failed: " . $response->body());
        }

        return [
            'status' => 'success',
            'ref_id' => $this->rawResponse['transId'] ?? $dto->authority,
            'tracking_code' => $this->rawResponse['clientRefId'] ?? $dto->authority,
        ];
    }

    public function getTransactionId(): ?string
    {
        return $this->rawResponse['code'] ?? null;
    }

    public function getTrackingCode(): ?string
    {
        return $this->rawResponse['transId'] ?? null;
    }

    public function getRawResponse(): ?array
    {
        return $this->rawResponse;
    }
}
