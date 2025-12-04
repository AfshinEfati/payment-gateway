<?php

namespace App\Payment\Gateways;

use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\Core\GatewayRequest;
use App\Payment\Core\RequestSender;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;
use App\Payment\PaymentException;
use App\Payment\Helpers\XmlBuilder;

class Sadad implements PaymentGatewayInterface
{
    protected array $config;
    protected ?string $rawResponse = null;
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
        $signData = $this->config['terminal_id'] . ';' . $dto->orderId . ';' . $dto->amount;
        $signature = $this->generateSignature($signData);
        $localDateTime = date('m/d/Y g:i:s a');

        $data = [
            'PaymentRequestModel' => [
                'MerchantId' => $this->config['merchant_id'],
                'TerminalId' => $this->config['terminal_id'],
                'Amount' => $dto->amount,
                'OrderId' => $dto->orderId,
                'LocalDateTime' => $localDateTime,
                'ReturnUrl' => $dto->callbackUrl,
                'SignData' => $signature,
                'AdditionalData' => $dto->description ?? '',
                'UserId' => 0,
                'ApplicationName' => $this->config['application_name'] ?? 'Payment',
            ]
        ];

        $body = XmlBuilder::build('PaymentRequest', $data, [], ['xmlns' => 'http://sadad.shaparak.ir/vpg/api/v0/Request']);
        $xml = XmlBuilder::soapEnvelope($body);

        $request = GatewayRequest::post('https://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest.asmx', $xml)
            ->asSoap('http://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest');

        $response = $this->sender->send($request);
        $this->rawResponse = $response->rawBody;

        if (!$response->success) {
            throw new PaymentException("Sadad Connection Error: " . $response->rawBody);
        }

        $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $response->rawBody);
        $xmlObj = simplexml_load_string($cleanXml);

        $result = $xmlObj->Body->PaymentRequestResponse->PaymentRequestResult ?? null;

        if (!$result) {
            throw new PaymentException("Sadad: Invalid response structure.");
        }

        $resCode = (int)$result->ResCode;
        $token = (string)$result->Token;
        $description = (string)$result->Description;

        if ($resCode != 0) {
            throw new PaymentException("Sadad initialization failed: " . $description);
        }

        return [
            'url' => 'https://sadad.shaparak.ir/VPG/Purchase?token=' . $token,
            'token' => $token,
        ];
    }

    /**
     * @throws PaymentException
     */
    public function verify(PaymentVerifyDTO $dto): array
    {
        $signData = $dto->authority;
        $signature = $this->generateSignature($signData);

        $data = [
            'VerifyTransactionModel' => [
                'Token' => $dto->authority,
                'SignData' => $signature,
            ]
        ];

        $body = XmlBuilder::build('VerifyTransaction', $data, [], ['xmlns' => 'http://sadad.shaparak.ir/vpg/api/v0/Advice']);
        $xml = XmlBuilder::soapEnvelope($body);

        $request = GatewayRequest::post('https://sadad.shaparak.ir/vpg/api/v0/Advice/Verify.asmx', $xml)
            ->asSoap('http://sadad.shaparak.ir/vpg/api/v0/Advice/VerifyTransaction');

        $response = $this->sender->send($request);
        $this->rawResponse = $response->rawBody;

        if (!$response->success) {
            throw new PaymentException("Sadad Verification Connection Error: " . $response->rawBody);
        }

        $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $response->rawBody);
        $xmlObj = simplexml_load_string($cleanXml);

        $result = $xmlObj->Body->VerifyTransactionResponse->VerifyTransactionResult ?? null;

        if (!$result) {
            throw new PaymentException("Sadad: Invalid verification response.");
        }

        $resCode = (int)$result->ResCode;
        $description = (string)$result->Description;

        if ($resCode != 0) {
            throw new PaymentException("Sadad Verification Error: " . $description);
        }

        return [
            'status' => 'success',
            'ref_id' => (string)$result->RetrivalRefNo ?? $dto->authority,
            'tracking_code' => (string)$result->SystemTraceNo ?? $dto->authority,
        ];
    }

    protected function generateSignature($data)
    {
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
        return ['body' => $this->rawResponse];
    }
}
