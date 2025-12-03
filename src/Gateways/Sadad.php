<?php

namespace PaymentGateway\Gateways;

use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\DTOs\PaymentRequestDTO;
use PaymentGateway\DTOs\PaymentVerifyDTO;
use PaymentGateway\Exceptions\PaymentException;
use SoapClient;

class Sadad implements PaymentGatewayInterface
{
    protected $config;
    protected $rawResponse;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(PaymentRequestDTO $dto): array
    {
        try {
            $client = new SoapClient('https://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest.asmx?WSDL');

            // Create signature (simplified - real implementation needs proper signing)
            $signData = $this->config['terminal_id'] . ';' . $dto->orderId . ';' . $dto->amount;

            $response = $client->PaymentRequest([
                'requestData' => [
                    'MerchantId' => $this->config['merchant_id'],
                    'TerminalId' => $this->config['terminal_id'],
                    'Amount' => $dto->amount,
                    'OrderId' => $dto->orderId,
                    'LocalDateTime' => date('m/d/Y g:i:s a'),
                    'ReturnUrl' => $dto->callbackUrl,
                    'SignData' => $this->generateSignature($signData),
                    'AdditionalData' => $dto->description ?? '',
                    'UserId' => 0,
                    'ApplicationName' => $this->config['application_name'] ?? 'Payment',
                ]
            ]);

            $result = $response->PaymentRequestResult;

            if ($result->ResCode != 0) {
                throw new PaymentException("Sadad initialization failed: " . $result->Description);
            }

            $token = $result->Token;

            return [
                'url' => 'https://sadad.shaparak.ir/VPG/Purchase?token=' . $token,
                'token' => $token,
            ];
        } catch (\Exception $e) {
            throw new PaymentException("Sadad Connection Error: " . $e->getMessage());
        }
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        try {
            $client = new SoapClient('https://sadad.shaparak.ir/vpg/api/v0/Advice/Verify.asmx?WSDL');

            $signData = $dto->authority;

            $response = $client->VerifyTransaction([
                'verifyData' => [
                    'Token' => $dto->authority,
                    'SignData' => $this->generateSignature($signData),
                ]
            ]);

            $result = $response->VerifyTransactionResult;

            if ($result->ResCode != 0) {
                throw new PaymentException("Sadad Verification Error: " . $result->Description);
            }

            return [
                'status' => 'success',
                'ref_id' => $result->RetrivalRefNo ?? $dto->authority,
                'tracking_code' => $result->SystemTraceNo ?? $dto->authority,
            ];
        } catch (\Exception $e) {
            throw new PaymentException("Sadad Connection Error: " . $e->getMessage());
        }
    }

    protected function generateSignature($data)
    {
        // Simplified signature - real implementation needs proper cryptographic signing with merchant key
        // This is a placeholder - users should implement based on Sadad documentation
        $key = $this->config['merchant_key'];
        return base64_encode(hash_hmac('sha256', $data, $key, true));
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
