<?php

namespace PaymentGateway\Gateways;

use Illuminate\Support\Facades\Http;
use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\DTOs\PaymentRequestDTO;
use PaymentGateway\DTOs\PaymentVerifyDTO;
use PaymentGateway\Exceptions\PaymentException;

class AsanPardakht implements PaymentGatewayInterface
{
    protected $config;
    protected $rawResponse;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function initialize(PaymentRequestDTO $dto): array
    {
        $username = $this->config['username'];
        $password = $this->config['password'];
        $orderId = $dto->orderId;
        $amount = $dto->amount;
        $localDate = date("Ymd His");
        $additionalData = $dto->description ?? "";
        $callbackUrl = $dto->callbackUrl;

        // 1,username,password,orderId,amount,localDate,additionalData,callbackUrl,0
        $req = "1,{$username},{$password},{$orderId},{$amount},{$localDate},{$additionalData},{$callbackUrl},0";

        $encryptedRequest = $this->encrypt($req);

        $url = 'https://services.asanpardakht.net/paygate/merchantservices.asmx';
        $action = 'http://tempuri.org/RequestOperation';

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <RequestOperation xmlns="http://tempuri.org/">
      <merchantConfigurationID>' . $this->config['merchant_config_id'] . '</merchantConfigurationID>
      <encryptedRequest>' . $encryptedRequest . '</encryptedRequest>
    </RequestOperation>
  </soap:Body>
</soap:Envelope>';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $action,
            ])->send('POST', $url, ['body' => $xml]);

            if ($response->failed()) {
                throw new PaymentException("AsanPardakht Connection Error: " . $response->body());
            }

            $responseBody = $response->body();
            $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
            $xmlObj = simplexml_load_string($cleanXml);

            $result = $xmlObj->Body->RequestOperationResponse->RequestOperationResult ?? null;

            if ($result === null) {
                throw new PaymentException("AsanPardakht: Invalid response structure.");
            }

            $responseStr = (string)$result;
            $this->rawResponse = ['result' => $responseStr];

            if (substr($responseStr, 0, 1) == '0') {
                $refId = substr($responseStr, 2);
                return [
                    'url' => 'https://asan.shaparak.ir/',
                    'token' => $refId,
                    'form_data' => [
                        'RefId' => $refId
                    ]
                ];
            } else {
                throw new PaymentException("AsanPardakht initialization failed. Code: $responseStr");
            }
        } catch (\Exception $e) {
            throw new PaymentException("AsanPardakht Error: " . $e->getMessage());
        }
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        $returningParams = $dto->metadata['ReturningParams'] ?? null;

        if (!$returningParams) {
            throw new PaymentException("AsanPardakht: ReturningParams is missing.");
        }

        $decryptedParams = $this->decrypt($returningParams);
        $retArr = explode(",", $decryptedParams);

        if (count($retArr) < 8) {
            throw new PaymentException("AsanPardakht: Invalid ReturningParams format.");
        }

        $amount = $retArr[0];
        $saleOrderId = $retArr[1];
        $refId = $retArr[2];
        $resCode = $retArr[3];
        $resMessage = $retArr[4];
        $payGateTranID = $retArr[5];
        $rrn = $retArr[6];

        $this->rawResponse = ['decrypted_params' => $retArr];

        if ($resCode != '0' && $resCode != '00') {
            throw new PaymentException("AsanPardakht Transaction Failed. Code: $resCode, Message: $resMessage");
        }

        // Verify
        try {
            $encryptedCredentials = $this->encrypt($this->config['username'] . ',' . $this->config['password']);

            $url = 'https://services.asanpardakht.net/paygate/merchantservices.asmx';
            $action = 'http://tempuri.org/RequestVerification';

            $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <RequestVerification xmlns="http://tempuri.org/">
      <merchantConfigurationID>' . $this->config['merchant_config_id'] . '</merchantConfigurationID>
      <encryptedCredentials>' . $encryptedCredentials . '</encryptedCredentials>
      <payGateTranID>' . $payGateTranID . '</payGateTranID>
    </RequestVerification>
  </soap:Body>
</soap:Envelope>';

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $action,
            ])->send('POST', $url, ['body' => $xml]);

            if ($response->failed()) {
                throw new PaymentException("AsanPardakht Verification Connection Error: " . $response->body());
            }

            $responseBody = $response->body();
            $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
            $xmlObj = simplexml_load_string($cleanXml);

            $verifyResult = (string)($xmlObj->Body->RequestVerificationResponse->RequestVerificationResult ?? '');

            if ($verifyResult != '500') {
                throw new PaymentException("AsanPardakht Verification Failed. Code: $verifyResult");
            }

            // Settlement
            $action = 'http://tempuri.org/RequestReconciliation';
            $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <RequestReconciliation xmlns="http://tempuri.org/">
      <merchantConfigurationID>' . $this->config['merchant_config_id'] . '</merchantConfigurationID>
      <encryptedCredentials>' . $encryptedCredentials . '</encryptedCredentials>
      <payGateTranID>' . $payGateTranID . '</payGateTranID>
    </RequestReconciliation>
  </soap:Body>
</soap:Envelope>';

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $action,
            ])->send('POST', $url, ['body' => $xml]);

            $responseBody = $response->body();
            $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
            $xmlObj = simplexml_load_string($cleanXml);

            $settleResult = (string)($xmlObj->Body->RequestReconciliationResponse->RequestReconciliationResult ?? '');

            if ($settleResult != '600') {
                throw new PaymentException("AsanPardakht Settlement Failed. Code: {$settleResult}");
            }

            return [
                'status' => 'success',
                'ref_id' => $refId,
                'tracking_code' => $rrn,
            ];
        } catch (\Exception $e) {
            throw new PaymentException("AsanPardakht Connection Error: " . $e->getMessage());
        }
    }

    protected function encrypt($string)
    {
        $url = 'https://services.asanpardakht.net/paygate/internalutils.asmx';
        $action = 'http://tempuri.org/EncryptInAES';

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <EncryptInAES xmlns="http://tempuri.org/">
      <aesKey>' . $this->config['key'] . '</aesKey>
      <aesVector>' . $this->config['iv'] . '</aesVector>
      <toBeEncrypted>' . $string . '</toBeEncrypted>
    </EncryptInAES>
  </soap:Body>
</soap:Envelope>';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $action,
            ])->send('POST', $url, ['body' => $xml]);

            if ($response->failed()) {
                throw new PaymentException("AsanPardakht Encryption Connection Error: " . $response->body());
            }

            $responseBody = $response->body();
            $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
            $xmlObj = simplexml_load_string($cleanXml);

            return (string)($xmlObj->Body->EncryptInAESResponse->EncryptInAESResult ?? '');
        } catch (\Exception $e) {
            throw new PaymentException("AsanPardakht Encryption Error: " . $e->getMessage());
        }
    }

    protected function decrypt($string)
    {
        $url = 'https://services.asanpardakht.net/paygate/internalutils.asmx';
        $action = 'http://tempuri.org/DecryptInAES';

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <DecryptInAES xmlns="http://tempuri.org/">
      <aesKey>' . $this->config['key'] . '</aesKey>
      <aesVector>' . $this->config['iv'] . '</aesVector>
      <toBeDecrypted>' . $string . '</toBeDecrypted>
    </DecryptInAES>
  </soap:Body>
</soap:Envelope>';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => $action,
            ])->send('POST', $url, ['body' => $xml]);

            if ($response->failed()) {
                throw new PaymentException("AsanPardakht Decryption Connection Error: " . $response->body());
            }

            $responseBody = $response->body();
            $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
            $xmlObj = simplexml_load_string($cleanXml);

            return (string)($xmlObj->Body->DecryptInAESResponse->DecryptInAESResult ?? '');
        } catch (\Exception $e) {
            throw new PaymentException("AsanPardakht Decryption Error: " . $e->getMessage());
        }
    }

    public function getTransactionId(): ?string
    {
        return $this->rawResponse['result'] ?? null;
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
