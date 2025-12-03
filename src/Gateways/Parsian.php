<?php

namespace PaymentGateway\Gateways;

use Illuminate\Support\Facades\Http;
use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\DTOs\PaymentRequestDTO;
use PaymentGateway\DTOs\PaymentVerifyDTO;
use PaymentGateway\Exceptions\PaymentException;

class Parsian implements PaymentGatewayInterface
{
    protected $config;
    protected $rawResponse;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(PaymentRequestDTO $dto): array
    {
        $url = 'https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?op=SalePaymentRequest';

        $soapRequest = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <SalePaymentRequest xmlns="https://pec.Shaparak.ir/NewIPGServices/Sale/SaleService">
      <requestData>
        <LoginAccount>' . $this->config['pin'] . '</LoginAccount>
        <Amount>' . $dto->amount . '</Amount>
        <OrderId>' . $dto->orderId . '</OrderId>
        <CallBackUrl>' . $dto->callbackUrl . '</CallBackUrl>
        <AdditionalData></AdditionalData>
        <Originator></Originator>
      </requestData>
    </SalePaymentRequest>
  </soap:Body>
</soap:Envelope>';

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => 'https://pec.Shaparak.ir/NewIPGServices/Sale/SaleService/SalePaymentRequest',
        ])->send('POST', $url, ['body' => $soapRequest]);

        // Parse SOAP response
        $xml = simplexml_load_string($response->body());
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $result = $xml->xpath('//soap:Body')[0];

        $token = (string)$result->SalePaymentRequestResponse->SalePaymentRequestResult->Token ?? null;
        $status = (int)$result->SalePaymentRequestResponse->SalePaymentRequestResult->Status ?? -1;

        $this->rawResponse = ['token' => $token, 'status' => $status];

        if ($status != 0 || !$token) {
            throw new PaymentException("Parsian initialization failed. Status: {$status}");
        }

        return [
            'url' => 'https://pec.shaparak.ir/NewIPG/?Token=' . $token,
            'token' => $token,
        ];
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        $url = 'https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx?op=ConfirmPayment';

        $soapRequest = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <ConfirmPayment xmlns="https://pec.Shaparak.ir/NewIPGServices/Confirm/ConfirmService">
      <requestData>
        <LoginAccount>' . $this->config['pin'] . '</LoginAccount>
        <Token>' . $dto->authority . '</Token>
      </requestData>
    </ConfirmPayment>
  </soap:Body>
</soap:Envelope>';

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => 'https://pec.Shaparak.ir/NewIPGServices/Confirm/ConfirmService/ConfirmPayment',
        ])->send('POST', $url, ['body' => $soapRequest]);

        // Parse SOAP response
        $xml = simplexml_load_string($response->body());
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $result = $xml->xpath('//soap:Body')[0];

        $status = (int)$result->ConfirmPaymentResponse->ConfirmPaymentResult->Status ?? -1;
        $rrn = (string)$result->ConfirmPaymentResponse->ConfirmPaymentResult->RRN ?? null;

        $this->rawResponse = ['status' => $status, 'rrn' => $rrn];

        if ($status != 0) {
            throw new PaymentException("Parsian verification failed. Status: {$status}");
        }

        return [
            'status' => 'success',
            'ref_id' => $rrn,
            'tracking_code' => $rrn,
        ];
    }

    public function getTransactionId(): ?string
    {
        return $this->rawResponse['token'] ?? null;
    }

    public function getTrackingCode(): ?string
    {
        return $this->rawResponse['rrn'] ?? null;
    }

    public function getRawResponse(): ?array
    {
        return $this->rawResponse;
    }
}
