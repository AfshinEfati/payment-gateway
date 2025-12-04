<?php

namespace App\Payment\Gateways;

use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\Core\GatewayRequest;
use App\Payment\Core\RequestSender;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;
use App\Payment\PaymentException;

class Pasargad implements PaymentGatewayInterface
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
        $url = 'https://pep.shaparak.ir/Api/v1/Payment/GetToken';

        $timeStamp = date('Y/m/d H:i:s');
        $invoiceNumber = $dto->orderId;
        $invoiceDate = date('Y/m/d H:i:s');
        $amount = $dto->amount;
        $terminalCode = $this->config['terminal_id'];
        $merchantCode = $this->config['merchant_id'];
        $redirectAddress = $dto->callbackUrl;

        // Data to sign
        $data = "#" . $merchantCode . "#" . $terminalCode . "#" . $invoiceNumber . "#" . $invoiceDate . "#" . $amount . "#" . $redirectAddress . "#" . ($dto->metadata['action'] ?? 1003) . "#" . $timeStamp . "#";

        // Generate RSA signature
        $sign = $this->sign($data);

        $request = GatewayRequest::post($url, [
            'InvoiceNumber' => $invoiceNumber,
            'InvoiceDate' => $invoiceDate,
            'Amount' => $amount,
            'TerminalCode' => $terminalCode,
            'MerchantCode' => $merchantCode,
            'RedirectAddress' => $redirectAddress,
            'TimeStamp' => $timeStamp,
            'Action' => $dto->metadata['action'] ?? 1003,
            'Sign' => $sign,
        ])->asForm();

        $response = $this->sender->send($request);
        $this->rawResponse = $response->data;

        if (!$response->success || !isset($this->rawResponse['IsSuccess']) || !$this->rawResponse['IsSuccess']) {
            throw new PaymentException("Pasargad initialization failed: " . ($this->rawResponse['Message'] ?? $response->rawBody));
        }

        $token = $this->rawResponse['Token'];

        return [
            'url' => 'https://pep.shaparak.ir/payment.aspx?n=' . $token,
            'token' => $token,
        ];
    }

    /**
     * @throws PaymentException
     */
    public function verify(PaymentVerifyDTO $dto): array
    {
        $url = 'https://pep.shaparak.ir/Api/v1/Payment/VerifyPayment';

        $timeStamp = date('Y/m/d H:i:s');
        $invoiceNumber = $dto->metadata['invoice_number'] ?? '';
        $invoiceDate = $dto->metadata['invoice_date'] ?? date('Y/m/d H:i:s');
        $amount = $dto->amount;
        $terminalCode = $this->config['terminal_id'];
        $merchantCode = $this->config['merchant_id'];

        // Data to sign
        $data = "#" . $merchantCode . "#" . $terminalCode . "#" . $invoiceNumber . "#" . $invoiceDate . "#" . $amount . "#" . $timeStamp . "#";

        // Generate RSA signature
        $sign = $this->sign($data);

        $request = GatewayRequest::post($url, [
            'InvoiceNumber' => $invoiceNumber,
            'InvoiceDate' => $invoiceDate,
            'Amount' => $amount,
            'TerminalCode' => $terminalCode,
            'MerchantCode' => $merchantCode,
            'TimeStamp' => $timeStamp,
            'Sign' => $sign,
        ])->asForm();

        $response = $this->sender->send($request);
        $this->rawResponse = $response->data;

        if (!$response->success || !isset($this->rawResponse['IsSuccess']) || !$this->rawResponse['IsSuccess']) {
            throw new PaymentException("Pasargad verification failed: " . ($this->rawResponse['Message'] ?? 'Unknown error'));
        }

        return [
            'status' => 'success',
            'ref_id' => $this->rawResponse['ReferenceNumber'] ?? $dto->authority,
            'tracking_code' => $this->rawResponse['TraceNumber'] ?? $dto->authority,
        ];
    }

    /**
     * @throws PaymentException
     */
    protected function sign($data)
    {
        // Load private key from config
        $privateKey = $this->config['private_key'];

        // If private key is a file path, load it
        if (file_exists($privateKey)) {
            $privateKey = file_get_contents($privateKey);
        }

        $pkeyId = openssl_pkey_get_private($privateKey);

        if (!$pkeyId) {
            throw new PaymentException("Pasargad: Unable to load private key");
        }

        openssl_sign($data, $signature, $pkeyId, OPENSSL_ALGO_SHA1);
        openssl_free_key($pkeyId);

        return base64_encode($signature);
    }

    public function getTransactionId(): ?string
    {
        return $this->rawResponse['Token'] ?? null;
    }

    public function getTrackingCode(): ?string
    {
        return $this->rawResponse['TraceNumber'] ?? null;
    }

    public function getRawResponse(): ?array
    {
        return $this->rawResponse;
    }
}
