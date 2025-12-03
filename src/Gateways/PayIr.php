<?php

namespace PaymentGateway\Gateways;

use Illuminate\Support\Facades\Http;
use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\DTOs\PaymentRequestDTO;
use PaymentGateway\DTOs\PaymentVerifyDTO;
use PaymentGateway\Exceptions\PaymentException;

class PayIr implements PaymentGatewayInterface
{
    protected $config;
    protected $rawResponse;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(PaymentRequestDTO $dto): array
    {
        $url = 'https://pay.ir/pg/send';

        $response = Http::asForm()->post($url, [
            'api' => $this->config['api_key'],
            'amount' => $dto->amount,
            'redirect' => $dto->callbackUrl,
            'mobile' => $dto->mobile,
            'factorNumber' => $dto->orderId,
            'description' => $dto->description,
        ]);

        $this->rawResponse = $response->json();

        if ($response->failed() || $this->rawResponse['status'] != 1) {
            throw new PaymentException("PAY.IR initialization failed: " . ($this->rawResponse['errorMessage'] ?? $response->body()));
        }

        $token = $this->rawResponse['token'];

        return [
            'url' => "https://pay.ir/pg/{$token}",
            'token' => $token,
        ];
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        $url = 'https://pay.ir/pg/verify';

        $response = Http::asForm()->post($url, [
            'api' => $this->config['api_key'],
            'token' => $dto->authority,
        ]);

        $this->rawResponse = $response->json();

        if ($response->failed() || $this->rawResponse['status'] != 1) {
            throw new PaymentException("PAY.IR verification failed: " . ($this->rawResponse['errorMessage'] ?? 'Unknown error'));
        }

        return [
            'status' => 'success',
            'ref_id' => $this->rawResponse['transId'],
            'tracking_code' => $this->rawResponse['factorNumber'] ?? $this->rawResponse['transId'],
        ];
    }

    public function getTransactionId(): ?string
    {
        return $this->rawResponse['token'] ?? null;
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
