<?php

namespace App\Payment\Gateways;

use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\Core\GatewayRequest;
use App\Payment\Core\RequestSender;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;
use App\Payment\PaymentException;
use App\Payment\Helpers\XmlBuilder;
use Exception;

class IranKish implements PaymentGatewayInterface
{
    protected array $config;
    protected ?array $rawResponse = null;
    protected RequestSender $sender;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->sender = new RequestSender();
    }

    /**
     * @throws PaymentException
     */
    public function initialize(PaymentRequestDTO $dto): array
    {
        $data = [
            'amount' => $dto->amount,
            'terminalId' => $this->config['terminal_id'],
            'identity' => $this->config['password'],
            'invoiceID' => $dto->orderId,
            'billID' => 0,
            'paymentId' => 0,
            'payload' => $dto->description ?? '',
            'callbackUrl' => $dto->callbackUrl,
        ];

        $body = XmlBuilder::build('MakeToken', $data, [], ['xmlns' => 'http://tempuri.org/']);
        $xml = XmlBuilder::soapEnvelope($body);

        $request = GatewayRequest::post('https://ikc.shaparak.ir/TToken/Tokens.svc', $xml)
            ->asSoap('http://tempuri.org/ITokens/MakeToken');

        $response = $this->sender->send($request);
        $responseBody = $response->rawBody;

        if (!$response->success) {
            throw new PaymentException("IranKish Connection Error: " . $responseBody);
        }

        $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
        $xmlObj = simplexml_load_string($cleanXml);

        $result = $xmlObj->Body->MakeTokenResponse->MakeTokenResult ?? null;

        if (!$result) {
            throw new PaymentException("IranKish: Invalid response structure.");
        }

        $token = (string)$result->token;
        $message = (string)$result->message;
        $isSuccess = (string)$result->result;

        if ($isSuccess !== 'true' || empty($token)) {
            throw new PaymentException("IranKish initialization failed: $message");
        }

        return [
            'url' => 'https://ikc.shaparak.ir/TPayment/Payment/Index/' . $token,
            'token' => $token,
        ];
    }

    /**
     * @throws PaymentException
     */
    public function verify(PaymentVerifyDTO $dto): array
    {
        $data = [
            'tokenIdentity' => $dto->metadata['token'] ?? '',
            'terminalId' => $this->config['terminal_id'],
            'retrievalReferenceNumber' => $dto->authority,
            'systemTraceAuditNumber' => $dto->metadata['system_trace_number'] ?? '',
        ];

        $body = XmlBuilder::build('KicccPaymentsVerification', $data, [], ['xmlns' => 'http://tempuri.org/']);
        $xml = XmlBuilder::soapEnvelope($body);

        $request = GatewayRequest::post('https://ikc.shaparak.ir/TVerify/Verify.svc', $xml)
            ->asSoap('http://tempuri.org/IVerify/KicccPaymentsVerification');

        $response = $this->sender->send($request);
        $responseBody = $response->rawBody;

        if (!$response->success) {
            throw new PaymentException("IranKish Verification Connection Error: " . $responseBody);
        }

        $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
        $xmlObj = simplexml_load_string($cleanXml);

        $result = $xmlObj->Body->KicccPaymentsVerificationResponse->KicccPaymentsVerificationResult ?? null;

        if (!$result) {
            throw new PaymentException("IranKish: Invalid verification response.");
        }

        $responseCode = (string)$result->ResponseCode;
        $description = (string)$result->Description;

        if ($responseCode != '00') {
            throw new PaymentException("IranKish Verification Error: " . ($description ?? $responseCode));
        }

        return [
            'status' => 'success',
            'ref_id' => (string)$result->RetrievalReferenceNumber ?? $dto->authority,
            'tracking_code' => (string)$result->SystemTraceAuditNumber ?? $dto->authority,
        ];
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
