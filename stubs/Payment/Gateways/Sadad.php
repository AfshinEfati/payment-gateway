<?php

namespace App\Payment\Gateways;

use Illuminate\Support\Facades\Http;
use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;
use App\Payment\PaymentException;

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
        $url = 'https://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest.asmx';
        $action = 'http://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest';

        $signData = $this->config['terminal_id'] . ';' . $dto->orderId . ';' . $dto->amount;
        $signature = $this->generateSignature($signData);
        $localDateTime = date('m/d/Y g:i:s a');

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <PaymentRequest xmlns="http://sadad.shaparak.ir/vpg/api/v0/Request">
      <PaymentRequestModel>
        <MerchantId>' . $this->config['merchant_id'] . '</MerchantId>
        <TerminalId>' . $this->config['terminal_id'] . '</TerminalId>
        <Amount>' . $dto->amount . '</Amount>
        <OrderId>' . $dto->orderId . '</OrderId>
        <LocalDateTime>' . $localDateTime . '</LocalDateTime>
        <ReturnUrl>' . $dto->callbackUrl . '</ReturnUrl>
        <SignData>' . $signature . '</SignData>
        <AdditionalData>' . ($dto->description ?? '') . '</AdditionalData>
        <UserId>0</UserId>
        <ApplicationName>' . ($this->config['application_name'] ?? 'Payment') . '</ApplicationName>
      </PaymentRequestModel>
    </PaymentRequest>
  </soap:Body>
</soap:Envelope>';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $action,
            ])->send('POST', $url, ['body' => $xml]);

            if ($response->failed()) {
                throw new PaymentException("Sadad Connection Error: " . $response->body());
            }

            $responseBody = $response->body();
            $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
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

        } catch (\Exception $e) {
            throw new PaymentException("Sadad Error: " . $e->getMessage());
        }
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        $url = 'https://sadad.shaparak.ir/vpg/api/v0/Advice/Verify.asmx';
        $action = 'http://sadad.shaparak.ir/vpg/api/v0/Advice/VerifyTransaction';

        $signData = $dto->authority;
        $signature = $this->generateSignature($signData);

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <VerifyTransaction xmlns="http://sadad.shaparak.ir/vpg/api/v0/Advice">
      <VerifyTransactionModel>
        <Token>' . $dto->authority . '</Token>
        <SignData>' . $signature . '</SignData>
      </VerifyTransactionModel>
    </VerifyTransaction>
  </soap:Body>
</soap:Envelope>';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $action,
            ])->send('POST', $url, ['body' => $xml]);

            if ($response->failed()) {
                throw new PaymentException("Sadad Verification Connection Error: " . $response->body());
            }

            $responseBody = $response->body();
            $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
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

        } catch (\Exception $e) {
            throw new PaymentException("Sadad Verification Error: " . $e->getMessage());
        }
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
        return $this->rawResponse;
    }
}
