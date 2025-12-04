<?php

namespace App\Payment\Gateways;

use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\Core\GatewayRequest;
use App\Payment\Core\RequestSender;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;
use App\Payment\PaymentException;
use App\Payment\Helpers\XmlBuilder;

class Saman implements PaymentGatewayInterface
{
    protected $config;
    protected $rawResponse;
    protected $sender;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->sender = new RequestSender();
    }

    public function initialize(PaymentRequestDTO $dto): array
    {
        // Saman uses a simple POST form submission
        // Generate token via their REST API
        $url = 'https://sep.shaparak.ir/onlinepg/onlinepg';

        $request = GatewayRequest::post($url, [
            'Action' => 'token',
            'TerminalId' => $this->config['terminal_id'],
            'Amount' => $dto->amount,
            'ResNum' => $dto->orderId,
            'RedirectUrl' => $dto->callbackUrl,
            'CellNumber' => $dto->mobile,
        ])->asForm();

        $response = $this->sender->send($request);
        $token = $response->rawBody;
        $this->rawResponse = ['token' => $token];

        if (empty($token) || strlen($token) < 10) {
            throw new PaymentException("Saman initialization failed: Invalid token");
        }

        // Return payment form URL
        return [
            'url' => 'https://sep.shaparak.ir/OnlinePG/OnlinePG',
            'token' => $token,
            'form_data' => [
                'Token' => $token,
                'RedirectURL' => $dto->callbackUrl,
            ]
        ];
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        // Saman verification via SOAP
        $url = 'https://sep.shaparak.ir/Payments/ReferencePayment.asmx';
        $action = 'http://sep.shaparak.ir/payments/VerifyTransaction';

        $data = [
            'VerifyTransaction' => [
                'RefNum' => $dto->authority,
                'MID' => $this->config['merchant_id'],
            ]
        ];

        $body = XmlBuilder::build('VerifyTransaction', $data['VerifyTransaction'], [], ['xmlns' => 'http://sep.shaparak.ir/payments']);
        $xml = XmlBuilder::soapEnvelope($body);

        $request = GatewayRequest::post($url, $xml)
            ->asSoap($action);

        $response = $this->sender->send($request);

        $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $response->rawBody);
        $xmlObj = simplexml_load_string($cleanXml);

        $result = $xmlObj->Body->VerifyTransactionResponse->VerifyTransactionResult ?? null;

        if ($result === null) {
            throw new PaymentException("Saman: Invalid verification response.");
        }

        $verifyResult = (float)$result;

        $this->rawResponse = ['verify_result' => $verifyResult];

        // Positive values mean success and represent the amount
        if ($verifyResult <= 0) {
            throw new PaymentException("Saman verification failed. Result: {$verifyResult}");
        }

        return [
            'status' => 'success',
            'ref_id' => $dto->authority,
            'tracking_code' => $dto->authority,
        ];
    }

    public function getTransactionId(): ?string
    {
        return $this->rawResponse['token'] ?? null;
    }

    public function getTrackingCode(): ?string
    {
        return $this->rawResponse['ref_num'] ?? null;
    }

    public function getRawResponse(): ?array
    {
        return $this->rawResponse;
    }
}
