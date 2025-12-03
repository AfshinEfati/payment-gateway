<?php

namespace PaymentGateway\Gateways;

use Illuminate\Support\Facades\Http;
use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\DTOs\PaymentRequestDTO;
use PaymentGateway\DTOs\PaymentVerifyDTO;
use PaymentGateway\Exceptions\PaymentException;

class Saman implements PaymentGatewayInterface
{
    protected $config;
    protected $rawResponse;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(PaymentRequestDTO $dto): array
    {
        // Saman uses a simple POST form submission
        // Generate token via their REST API
        $url = 'https://sep.shaparak.ir/onlinepg/onlinepg';

        $response = Http::asForm()->post($url, [
            'Action' => 'token',
            'TerminalId' => $this->config['terminal_id'],
            'Amount' => $dto->amount,
            'ResNum' => $dto->orderId,
            'RedirectUrl' => $dto->callbackUrl,
            'CellNumber' => $dto->mobile,
        ]);

        $token = $response->body();
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
        $url = 'https://sep.shaparak.ir/Payments/ReferencePayment.asmx?WSDL';

        $soapRequest = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <VerifyTransaction xmlns="http://sep.shaparak.ir/payments">
      <RefNum>' . $dto->authority . '</RefNum>
      <MID>' . $this->config['merchant_id'] . '</MID>
    </VerifyTransaction>
  </soap:Body>
</soap:Envelope>';

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => 'http://sep.shaparak.ir/payments/VerifyTransaction',
        ])->send('POST', $url, ['body' => $soapRequest]);

        // Parse SOAP response
        $xml = simplexml_load_string($response->body());
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $result = $xml->xpath('//soap:Body')[0];

        $verifyResult = (float)$result->VerifyTransactionResponse->VerifyTransactionResult ?? -1;

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
