<?php

namespace PaymentGateway\Gateways;

use Illuminate\Support\Facades\Http;
use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\DTOs\PaymentRequestDTO;
use PaymentGateway\DTOs\PaymentVerifyDTO;
use PaymentGateway\Exceptions\PaymentException;

class NextPay implements PaymentGatewayInterface
{
    protected $config;
    protected $rawResponse;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(PaymentRequestDTO $dto): array
    {
        $url = 'https://nextpay.org/nx/gateway/token';

        $response = Http::asForm()->post($url, [
            'api_key' => $this->config['api_key'],
            'order_id' => $dto->orderId,
            'amount' => $dto->amount,
            'callback_uri' => $dto->callbackUrl,
            'customer_phone' => $dto->mobile,
        ]);

        $this->rawResponse = $response->json();

        if ($response->failed() || $this->rawResponse['code'] != 0) {
            throw new PaymentException("NextPay initialization failed: " . ($this->rawResponse['message'] ?? $response->body()));
        }

        $token = $this->rawResponse['trans_id'];

        return [
            'url' => "https://nextpay.org/nx/gateway/payment/{$token}",
            'token' => $token,
        ];
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        $url = 'https://nextpay.org/nx/gateway/verify';

        $response = Http::asForm()->post($url, [
            'api_key' => $this->config['api_key'],
            'trans_id' => $dto->authority,
            'amount' => $dto->amount,
        ]);

        $this->rawResponse = $response->json();

        if ($response->failed() || $this->rawResponse['code'] != 0) {
            throw new PaymentException("NextPay verification failed: " . ($this->rawResponse['message'] ?? 'Unknown error'));
        }

        return [
            'status' => 'success',
            'ref_id' => $this->rawResponse['Shaparak_Ref_Id'] ?? $dto->authority,
            'tracking_code' => $this->rawResponse['order_id'] ?? $dto->authority,
        ];
    }

    public function getTransactionId(): ?string
    {
        return $this->rawResponse['trans_id'] ?? null;
    }

    public function getTrackingCode(): ?string
    {
        return $this->rawResponse['order_id'] ?? null;
    }

    public function getRawResponse(): ?array
    {
        return $this->rawResponse;
    }
}
