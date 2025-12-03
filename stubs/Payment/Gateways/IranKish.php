<?php

namespace App\Payment\Gateways;

use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;
use App\Payment\PaymentException;
use SoapClient;

class IranKish implements PaymentGatewayInterface
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
            $client = new SoapClient('https://ikc.shaparak.ir/TToken/Tokens.svc?wsdl');

            $response = $client->MakeToken([
                'terminalId' => $this->config['terminal_id'],
                'password' => $this->config['password'],
                'amount' => $dto->amount,
                'orderId' => $dto->orderId,
                'localDate' => date('Ymd His'),
                'localTime' => date('His'),
                'additionalData' => $dto->description ?? '',
                'callBackUrl' => $dto->callbackUrl,
                'payerId' => 0,
            ]);

            $result = $response->MakeTokenResult;

            if (!isset($result->Token) || empty($result->Token)) {
                throw new PaymentException("IranKish initialization failed: " . ($result->Message ?? 'Unknown error'));
            }

            $token = $result->Token;

            return [
                'url' => 'https://ikc.shaparak.ir/TPayment/Payment/Index/' . $token,
                'token' => $token,
            ];
        } catch (\Exception $e) {
            throw new PaymentException("IranKish Connection Error: " . $e->getMessage());
        }
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        try {
            $client = new SoapClient('https://ikc.shaparak.ir/TVerify/Verify.svc?wsdl');

            $response = $client->KicccPaymentsVerification([
                'terminalId' => $this->config['terminal_id'],
                'retrievalReferenceNumber' => $dto->authority,
                'systemTraceAuditNumber' => $dto->metadata['system_trace_number'] ?? '',
                'tokenIdentity' => $dto->metadata['token'] ?? '',
            ]);

            $result = $response->KicccPaymentsVerificationResult;

            if ($result->ResponseCode != '00') {
                throw new PaymentException("IranKish Verification Error: " . ($result->Description ?? $result->ResponseCode));
            }

            return [
                'status' => 'success',
                'ref_id' => $result->RetrievalReferenceNumber ?? $dto->authority,
                'tracking_code' => $result->SystemTraceAuditNumber ?? $dto->authority,
            ];
        } catch (\Exception $e) {
            throw new PaymentException("IranKish Connection Error: " . $e->getMessage());
        }
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
