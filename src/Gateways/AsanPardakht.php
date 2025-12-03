<?php

namespace PaymentGateway\Gateways;

use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\DTOs\PaymentRequestDTO;
use PaymentGateway\DTOs\PaymentVerifyDTO;
use PaymentGateway\Exceptions\PaymentException;
use SoapClient;

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

        try {
            $client = new SoapClient('https://services.asanpardakht.net/paygate/merchantservices.asmx?WSDL', [
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);

            $params = [
                'merchantConfigurationID' => $this->config['merchant_config_id'],
                'encryptedRequest' => $encryptedRequest
            ];

            $result = $client->RequestOperation($params);
            $response = $result->RequestOperationResult;

            $this->rawResponse = ['result' => $response];

            if (substr($response, 0, 1) == '0') {
                $refId = substr($response, 2);
                return [
                    'url' => 'https://asan.shaparak.ir/',
                    'token' => $refId,
                    'form_data' => [
                        'RefId' => $refId
                    ]
                ];
            } else {
                throw new PaymentException("AsanPardakht initialization failed. Code: {$response}");
            }

        } catch (\Exception $e) {
            throw new PaymentException("AsanPardakht Connection Error: " . $e->getMessage());
        }
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        // The ReturningParams are usually passed in the request, but here we expect them in the DTO metadata or we need to access request
        // Since the interface only passes DTO, we assume the controller puts 'ReturningParams' into metadata or authority
        // Usually authority is the token/RefId.
        // But AsanPardakht sends a big encrypted string 'ReturningParams'.
        
        $returningParams = $dto->metadata['ReturningParams'] ?? null;

        if (!$returningParams) {
             // If not in metadata, maybe the user passed it as authority?
             // But authority is usually the RefId.
             // Let's assume the user of the package will put $_POST['ReturningParams'] into the DTO.
             throw new PaymentException("AsanPardakht: ReturningParams is missing.");
        }

        $decryptedParams = $this->decrypt($returningParams);
        $retArr = explode(",", $decryptedParams);

        // Amount,SaleOrderId,RefId,ResCode,ResMessage,PayGateTranID,RRN,LastFourDigitOfPAN
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
            throw new PaymentException("AsanPardakht Transaction Failed. Code: {$resCode}, Message: {$resMessage}");
        }

        // Verify
        try {
            $client = new SoapClient('https://services.asanpardakht.net/paygate/merchantservices.asmx?WSDL', [
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);

            $encryptedCredentials = $this->encrypt("{$this->config['username']},{$this->config['password']}");

            $params = [
                'merchantConfigurationID' => $this->config['merchant_config_id'],
                'encryptedCredentials' => $encryptedCredentials,
                'payGateTranID' => $payGateTranID
            ];

            $result = $client->RequestVerification($params);
            $verifyResult = $result->RequestVerificationResult;

            if ($verifyResult != '500') {
                throw new PaymentException("AsanPardakht Verification Failed. Code: {$verifyResult}");
            }

            // Settlement
            $result = $client->RequestReconciliation($params);
            $settleResult = $result->RequestReconciliationResult;

            if ($settleResult != '600') {
                 // Note: Sometimes settlement might fail but verification was success. 
                 // But usually we want both.
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
        try {
            $client = new SoapClient("https://services.asanpardakht.net/paygate/internalutils.asmx?WSDL", [
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);

            $params = [
                'aesKey' => $this->config['key'],
                'aesVector' => $this->config['iv'],
                'toBeEncrypted' => $string
            ];

            $result = $client->EncryptInAES($params);
            return $result->EncryptInAESResult;
        } catch (\Exception $e) {
            throw new PaymentException("AsanPardakht Encryption Error: " . $e->getMessage());
        }
    }

    protected function decrypt($string)
    {
        try {
            $client = new SoapClient("https://services.asanpardakht.net/paygate/internalutils.asmx?WSDL", [
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);

            $params = [
                'aesKey' => $this->config['key'],
                'aesVector' => $this->config['iv'],
                'toBeDecrypted' => $string
            ];

            $result = $client->DecryptInAES($params);
            return $result->DecryptInAESResult;
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
