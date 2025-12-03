<?php

namespace App\Payment\Gateways;

use Illuminate\Support\Facades\Http;
use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;
use App\Payment\PaymentException;

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
        $url = 'https://ikc.shaparak.ir/TToken/Tokens.svc';
        $action = 'http://tempuri.org/ITokens/MakeToken';

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <MakeToken xmlns="http://tempuri.org/">
      <amount>' . $dto->amount . '</amount>
      <terminalId>' . $this->config['terminal_id'] . '</terminalId>
      <identity>' . $this->config['password'] . '</identity>
      <invoiceID>' . $dto->orderId . '</invoiceID>
      <billID>0</billID>
      <paymentId>0</paymentId>
      <payload>' . ($dto->description ?? '') . '</payload>
      <callbackUrl>' . $dto->callbackUrl . '</callbackUrl>
    </MakeToken>
  </soap:Body>
</soap:Envelope>';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $action,
            ])->send('POST', $url, ['body' => $xml]);

            if ($response->failed()) {
                throw new PaymentException("IranKish Connection Error: " . $response->body());
            }

            $responseBody = $response->body();
            
            // Simple XML parsing
            $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
            $xmlObj = simplexml_load_string($cleanXml);
            
            // Navigate to result
            // Structure: Envelope -> Body -> MakeTokenResponse -> MakeTokenResult -> result (boolean) / message / token
            $result = $xmlObj->Body->MakeTokenResponse->MakeTokenResult ?? null;

            if (!$result) {
                 throw new PaymentException("IranKish: Invalid response structure.");
            }

            $token = (string)$result->token;
            $message = (string)$result->message;
            $isSuccess = (string)$result->result;

            if ($isSuccess !== 'true' || empty($token)) {
                throw new PaymentException("IranKish initialization failed: " . $message);
            }

            return [
                'url' => 'https://ikc.shaparak.ir/TPayment/Payment/Index/' . $token,
                'token' => $token,
            ];

        } catch (\Exception $e) {
            throw new PaymentException("IranKish Error: " . $e->getMessage());
        }
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        $url = 'https://ikc.shaparak.ir/TVerify/Verify.svc';
        $action = 'http://tempuri.org/IVerify/KicccPaymentsVerification';

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <KicccPaymentsVerification xmlns="http://tempuri.org/">
      <tokenIdentity>' . ($dto->metadata['token'] ?? '') . '</tokenIdentity>
      <terminalId>' . $this->config['terminal_id'] . '</terminalId>
      <retrievalReferenceNumber>' . $dto->authority . '</retrievalReferenceNumber>
      <systemTraceAuditNumber>' . ($dto->metadata['system_trace_number'] ?? '') . '</systemTraceAuditNumber>
    </KicccPaymentsVerification>
  </soap:Body>
</soap:Envelope>';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $action,
            ])->send('POST', $url, ['body' => $xml]);

            if ($response->failed()) {
                throw new PaymentException("IranKish Verification Connection Error: " . $response->body());
            }

            $responseBody = $response->body();
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

        } catch (\Exception $e) {
            throw new PaymentException("IranKish Verification Error: " . $e->getMessage());
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
