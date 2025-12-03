<?php

namespace App\Payment\Gateways;

use Illuminate\Support\Facades\Http;
use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;
use App\Payment\PaymentException;

class Zarinpal implements PaymentGatewayInterface
{
    protected $config;
    protected $rawResponse;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(PaymentRequestDTO $dto): array
    {
        $url = $this->config['sandbox']
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/request.json'
            : 'https://api.zarinpal.com/pg/v4/payment/request.json';

        $response = Http::post($url, [
            'merchant_id' => $this->config['merchant_id'],
            'amount' => $dto->amount,
            'callback_url' => $dto->callbackUrl,
            'description' => $dto->description ?? 'Payment',
            'metadata' => [
                'mobile' => $dto->mobile,
                'email' => $dto->email,
            ],
        ]);

        $this->rawResponse = $response->json();

        if ($response->failed()) {
            throw new PaymentException("Zarinpal initialization failed: " . $response->body());
        }

        $result = $response->json();

        if (isset($result['errors']) && !empty($result['errors'])) {
            throw new PaymentException("Zarinpal Error: " . json_encode($result['errors']));
        }

        if (($result['data']['code'] ?? 0) != 100) {
            throw new PaymentException("Zarinpal Error Code: " . ($result['data']['code'] ?? 'Unknown'));
        }

        $authority = $result['data']['authority'];
        $startPayUrl = $this->config['sandbox']
            ? "https://sandbox.zarinpal.com/pg/StartPay/{$authority}"
            : "https://www.zarinpal.com/pg/StartPay/{$authority}";

        return [
            'url' => $startPayUrl,
            'token' => $authority,
        ];
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        $url = $this->config['sandbox']
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json'
            : 'https://api.zarinpal.com/pg/v4/payment/verify.json';

        $response = Http::post($url, [
            'merchant_id' => $this->config['merchant_id'],
            'amount' => $dto->amount,
            'authority' => $dto->authority,
        ]);

        $this->rawResponse = $response->json();

        if ($response->failed()) {
            throw new PaymentException("Zarinpal verification failed: " . $response->body());
        }

        $result = $response->json();

        if (isset($result['errors']) && !empty($result['errors'])) {
            throw new PaymentException("Zarinpal Verification Error: " . json_encode($result['errors']));
        }

        $code = $result['data']['code'] ?? 0;

        if ($code == 100 || $code == 101) {
            return [
                'status' => 'success',
                'ref_id' => $result['data']['ref_id'],
                'tracking_code' => $result['data']['ref_id'],
            ];
        }

        throw new PaymentException("Zarinpal Verification Failed. Code: {$code}");
    }

    public function getTransactionId(): ?string
    {
        return null;
    }

    public function getTrackingCode(): ?string
    {
        return null;
    }

    public function getRawResponse(): ?array
    {
        return $this->rawResponse;
    }
}
