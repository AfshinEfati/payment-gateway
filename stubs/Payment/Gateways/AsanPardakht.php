<?php

namespace App\Payment\Gateways;

use Illuminate\Support\Facades\Http;
use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\Core\GatewayRequest;
use App\Payment\Core\RequestSender;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;
use App\Payment\PaymentException;
use App\Payment\Helpers\XmlBuilder;
use Exception;

class AsanPardakht implements PaymentGatewayInterface
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
        $username = $this->config['username'];
        $password = $this->config['password'];
        $orderId = $dto->orderId;
        $amount = $dto->amount;
        $localDate = date("Ymd His");
        $additionalData = $dto->description ?? "";
        $callbackUrl = $dto->callbackUrl;

        // 1,username,password,orderId,amount,localDate,additionalData,callbackUrl,0
        $req = "1,$username,$password,$orderId,$amount,$localDate,$additionalData,$callbackUrl,0";

        $encryptedRequest = $this->encrypt($req);

        $data = [
            'merchantConfigurationID' => $this->config['merchant_config_id'],
            'encryptedRequest' => $encryptedRequest,
        ];

        $body = XmlBuilder::build('RequestOperation', $data, [], ['xmlns' => 'http://tempuri.org/']);
        $xml = XmlBuilder::soapEnvelope($body);

        $request = GatewayRequest::post('https://services.asanpardakht.net/paygate/merchantservices.asmx', $xml)
            ->asSoap('http://tempuri.org/RequestOperation');

        $response = $this->sender->send($request);
        $responseBody = $response->rawBody;

        if (!$response->success) {
            throw new PaymentException("AsanPardakht Connection Error: " . $responseBody);
        }

        $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
        $xmlObj = simplexml_load_string($cleanXml);

        $result = $xmlObj->Body->RequestOperationResponse->RequestOperationResult ?? null;

        if ($result === null) {
            throw new PaymentException("AsanPardakht: Invalid response structure.");
        }

        $responseStr = (string)$result;
        $this->rawResponse = ['result' => $responseStr];

        if (str_starts_with($responseStr, '0')) {
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
    }

    /**
     * @throws PaymentException
     */
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

        // $amount = $retArr[0]; // Unused
        // $saleOrderId = $retArr[1]; // Unused
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
        $encryptedCredentials = $this->encrypt("{$this->config['username']},{$this->config['password']}");

        $verifyData = [
            'merchantConfigurationID' => $this->config['merchant_config_id'],
            'encryptedCredentials' => $encryptedCredentials,
            'payGateTranID' => $payGateTranID,
        ];

        $verifyBody = XmlBuilder::build('RequestVerification', $verifyData, [], ['xmlns' => 'http://tempuri.org/']);
        $verifyXml = XmlBuilder::soapEnvelope($verifyBody);

        $request = GatewayRequest::post('https://services.asanpardakht.net/paygate/merchantservices.asmx', $verifyXml)
            ->asSoap('http://tempuri.org/RequestVerification');

        $response = $this->sender->send($request);
        $responseBody = $response->rawBody;

        if (!$response->success) {
            throw new PaymentException("AsanPardakht Verification Connection Error: " . $responseBody);
        }

        $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
        $xmlObj = simplexml_load_string($cleanXml);

        $verifyResult = (string)($xmlObj->Body->RequestVerificationResponse->RequestVerificationResult ?? '');

        if ($verifyResult != '500') {
            throw new PaymentException("AsanPardakht Verification Failed. Code: $verifyResult");
        }

        // Settlement
        $settleData = [
            'merchantConfigurationID' => $this->config['merchant_config_id'],
            'encryptedCredentials' => $encryptedCredentials,
            'payGateTranID' => $payGateTranID,
        ];

        $settleBody = XmlBuilder::build('RequestReconciliation', $settleData, [], ['xmlns' => 'http://tempuri.org/']);
        $settleXml = XmlBuilder::soapEnvelope($settleBody);

        $settleRequest = GatewayRequest::post('https://services.asanpardakht.net/paygate/merchantservices.asmx', $settleXml)
            ->asSoap('http://tempuri.org/RequestReconciliation');

        $responseSettle = $this->sender->send($settleRequest);
        $responseBodySettle = $responseSettle->rawBody;

        $cleanXmlSettle = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBodySettle);
        $xmlObjSettle = simplexml_load_string($cleanXmlSettle);

        $settleResult = (string)($xmlObjSettle->Body->RequestReconciliationResponse->RequestReconciliationResult ?? '');

        if ($settleResult != '600') {
            throw new PaymentException("AsanPardakht Settlement Failed. Code: $settleResult");
        }

        return [
            'status' => 'success',
            'ref_id' => $refId,
            'tracking_code' => $rrn,
        ];
    }

    /**
     * @throws PaymentException
     */
    protected function encrypt($string)
    {
        $data = [
            'aesKey' => $this->config['key'],
            'aesVector' => $this->config['iv'],
            'toBeEncrypted' => $string,
        ];

        $body = XmlBuilder::build('EncryptInAES', $data, [], ['xmlns' => 'http://tempuri.org/']);
        $xml = XmlBuilder::soapEnvelope($body);

        $request = GatewayRequest::post('https://services.asanpardakht.net/paygate/internalutils.asmx', $xml)
            ->asSoap('http://tempuri.org/EncryptInAES');

        $response = $this->sender->send($request);
        $responseBody = $response->rawBody;

        if (!$response->success) {
            throw new PaymentException("AsanPardakht Encryption Connection Error: " . $responseBody);
        }

        $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
        $xmlObj = simplexml_load_string($cleanXml);

        return (string)($xmlObj->Body->EncryptInAESResponse->EncryptInAESResult ?? '');
    }

    /**
     * @throws PaymentException
     */
    protected function decrypt($string)
    {
        $data = [
            'aesKey' => $this->config['key'],
            'aesVector' => $this->config['iv'],
            'toBeDecrypted' => $string,
        ];

        $body = XmlBuilder::build('DecryptInAES', $data, [], ['xmlns' => 'http://tempuri.org/']);
        $xml = XmlBuilder::soapEnvelope($body);

        $request = GatewayRequest::post('https://services.asanpardakht.net/paygate/internalutils.asmx', $xml)
            ->asSoap('http://tempuri.org/DecryptInAES');

        $response = $this->sender->send($request);
        $responseBody = $response->rawBody;

        if (!$response->success) {
            throw new PaymentException("AsanPardakht Decryption Connection Error: " . $responseBody);
        }

        $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $responseBody);
        $xmlObj = simplexml_load_string($cleanXml);

        return (string)($xmlObj->Body->DecryptInAESResponse->DecryptInAESResult ?? '');
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
