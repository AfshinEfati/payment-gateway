<?php

namespace PaymentGateway\Gateways;

use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\DTOs\PaymentRequestDTO;
use PaymentGateway\DTOs\PaymentVerifyDTO;
use PaymentGateway\Exceptions\PaymentException;
use SoapClient;

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
        $amount = (int) $dto->amount; // Mellat requires integer
        $localDate = date('Ymd');
        $localTime = date('His');
        $additionalData = $dto->description ?? '';
        $callBackUrl = $dto->callbackUrl;
        $payerId = 0;

        try {
            $client = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
            $response = $client->bpPayRequest([
                'terminalId' => $terminalId,
                'userName' => $userName,
                'userPassword' => $password,
                'orderId' => $orderId,
                'amount' => $amount,
                'localDate' => $localDate,
                'localTime' => $localTime,
                'additionalData' => $additionalData,
                'callBackUrl' => $callBackUrl,
                'payerId' => $payerId,
            ]);

            $result = $response->return;
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
            throw new PaymentException("Mellat Connection Error: " . $e->getMessage());
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

        try {
            $client = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');

            // 1. Verify
            $response = $client->bpVerifyRequest([
                'terminalId' => $terminalId,
                'userName' => $userName,
                'userPassword' => $password,
                'orderId' => $orderId,
                'saleOrderId' => $saleOrderId,
                'saleReferenceId' => $saleReferenceId,
            ]);

            $result = $response->return;

            if ($result !== '0') {
                throw new PaymentException("Mellat Verify Error: {$result}");
            }

            // 2. Settle
            $settleResponse = $client->bpSettleRequest([
                'terminalId' => $terminalId,
                'userName' => $userName,
                'userPassword' => $password,
                'orderId' => $orderId,
                'saleOrderId' => $saleOrderId,
                'saleReferenceId' => $saleReferenceId,
            ]);

            $settleResult = $settleResponse->return;

            if ($settleResult !== '0' && $settleResult !== '45') {
                throw new PaymentException("Mellat Settle Error: {$settleResult}");
            }

            return [
                'status' => 'success',
                'ref_id' => $saleReferenceId,
                'tracking_code' => $saleReferenceId,
            ];
        } catch (\Exception $e) {
            throw new PaymentException("Mellat Connection Error: " . $e->getMessage());
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
