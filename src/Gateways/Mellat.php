<?php

namespace PaymentGateway\Gateways;

use Illuminate\Support\Facades\Http;
use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\DTOs\PaymentRequestDTO;
use PaymentGateway\DTOs\PaymentVerifyDTO;
use PaymentGateway\Exceptions\PaymentException;

class Mellat implements PaymentGatewayInterface
{
    protected $config;
    protected $rawResponse;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(PaymentRequestDTO $dto): array
    {
        $terminalId = $this->config['terminal_id'];
        $userName = $this->config['username'];
        $password = $this->config['password'];
        $orderId = $dto->orderId;
        $amount = (int) $dto->amount;
        $localDate = date('Ymd');
        $localTime = date('His');
        $additionalData = $dto->description ?? '';
        $callBackUrl = $dto->callbackUrl;
        $payerId = 0;

        $url = 'https://bpm.shaparak.ir/pgwchannel/services/pgw';
        $action = ''; 

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <bpPayRequest xmlns="http://interfaces.core.sw.bps.com/">
      <terminalId>' . $terminalId . '</terminalId>
      <userName>' . $userName . '</userName>
      <userPassword>' . $password . '</userPassword>
      <orderId>' . $orderId . '</orderId>
      <amount>' . $amount . '</amount>
      <localDate>' . $localDate . '</localDate>
      <localTime>' . $localTime . '</localTime>
      <additionalData>' . $additionalData . '</additionalData>
      <callBackUrl>' . $callBackUrl . '</callBackUrl>
      <payerId>' . $payerId . '</payerId>
    </bpPayRequest>
  </soap:Body>
</soap:Envelope>';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
            ])->send('POST', $url, ['body' => $xml]);

            if ($response->failed()) {
                throw new PaymentException("Mellat Connection Error: " . $response->body());
            }

            $responseBody = $response->body();
            $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:', 'ns1:'], '', $responseBody);
            $xmlObj = simplexml_load_string($cleanXml);
            
            $result = (string)($xmlObj->Body->bpPayRequestResponse->return ?? '');
            $res = explode(',', $result);
            $code = $res[0];

            if ($code !== '0') {
                throw new PaymentException("Mellat Initialization Error: {$code}");
            }

            $refId = $res[1];

            return [
                'url' => 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat',
                'token' => $refId,
            ];
        } catch (\Exception $e) {
            throw new PaymentException("Mellat Error: " . $e->getMessage());
        }
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        $terminalId = $this->config['terminal_id'];
        $userName = $this->config['username'];
        $password = $this->config['password'];
        $orderId = $dto->metadata['order_id'] ?? null;
        $saleOrderId = $dto->metadata['sale_order_id'] ?? null;
        $saleReferenceId = $dto->authority;

        $url = 'https://bpm.shaparak.ir/pgwchannel/services/pgw';

        try {
            // 1. Verify
            $xmlVerify = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <bpVerifyRequest xmlns="http://interfaces.core.sw.bps.com/">
      <terminalId>' . $terminalId . '</terminalId>
      <userName>' . $userName . '</userName>
      <userPassword>' . $password . '</userPassword>
      <orderId>' . $orderId . '</orderId>
      <saleOrderId>' . $saleOrderId . '</saleOrderId>
      <saleReferenceId>' . $saleReferenceId . '</saleReferenceId>
    </bpVerifyRequest>
  </soap:Body>
</soap:Envelope>';

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
            ])->send('POST', $url, ['body' => $xmlVerify]);

            if ($response->failed()) {
                throw new PaymentException("Mellat Verify Connection Error: " . $response->body());
            }

            $responseBody = $response->body();
            $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:', 'ns1:'], '', $responseBody);
            $xmlObj = simplexml_load_string($cleanXml);
            
            $result = (string)($xmlObj->Body->bpVerifyRequestResponse->return ?? '');

            if ($result !== '0') {
                throw new PaymentException("Mellat Verify Error: {$result}");
            }

            // 2. Settle
            $xmlSettle = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <bpSettleRequest xmlns="http://interfaces.core.sw.bps.com/">
      <terminalId>' . $terminalId . '</terminalId>
      <userName>' . $userName . '</userName>
      <userPassword>' . $password . '</userPassword>
      <orderId>' . $orderId . '</orderId>
      <saleOrderId>' . $saleOrderId . '</saleOrderId>
      <saleReferenceId>' . $saleReferenceId . '</saleReferenceId>
    </bpSettleRequest>
  </soap:Body>
</soap:Envelope>';

            $responseSettle = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
            ])->send('POST', $url, ['body' => $xmlSettle]);

            $responseBodySettle = $responseSettle->body();
            $cleanXmlSettle = str_ireplace(['soap:', 's:', 'xmlns:', 'ns1:'], '', $responseBodySettle);
            $xmlObjSettle = simplexml_load_string($cleanXmlSettle);
            
            $settleResult = (string)($xmlObjSettle->Body->bpSettleRequestResponse->return ?? '');

            if ($settleResult !== '0' && $settleResult !== '45') {
                throw new PaymentException("Mellat Settle Error: {$settleResult}");
            }

            return [
                'status' => 'success',
                'ref_id' => $saleReferenceId,
                'tracking_code' => $saleReferenceId,
            ];
        } catch (\Exception $e) {
            throw new PaymentException("Mellat Error: " . $e->getMessage());
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
