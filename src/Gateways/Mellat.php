<?php

namespace PaymentGateway\Gateways;

use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\Core\GatewayRequest;
use PaymentGateway\Core\RequestSender;
use PaymentGateway\DTOs\PaymentRequestDTO;
use PaymentGateway\DTOs\PaymentVerifyDTO;
use PaymentGateway\Exceptions\PaymentException;
use PaymentGateway\Helpers\XmlBuilder;

class Mellat implements PaymentGatewayInterface
{
    protected $config;
    protected $rawResponse;
    protected $sender;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->sender = new RequestSender();
    }

    public function initialize(PaymentRequestDTO $dto): array
    {
        $data = [
            'terminalId' => $this->config['terminal_id'],
            'userName' => $this->config['username'],
            'userPassword' => $this->config['password'],
            'orderId' => $dto->orderId,
            'amount' => (int) $dto->amount,
            'localDate' => date('Ymd'),
            'localTime' => date('His'),
            'additionalData' => $dto->description ?? '',
            'callBackUrl' => $dto->callbackUrl,
            'payerId' => 0,
        ];

        // Build SOAP Body
        $body = XmlBuilder::build('bpPayRequest', $data, [], ['xmlns' => 'http://interfaces.core.sw.bps.com/']);
        $xml = XmlBuilder::soapEnvelope($body);

        $request = GatewayRequest::post('https://bpm.shaparak.ir/pgwchannel/services/pgw', $xml)
            ->asSoap();

        $response = $this->sender->send($request);
        $this->rawResponse = $response->rawBody;

        if (!$response->success) {
            throw new PaymentException("Mellat Connection Error: " . $response->rawBody);
        }

        $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:', 'ns1:'], '', $response->rawBody);
        $xmlObj = simplexml_load_string($cleanXml);

        $result = (string)($xmlObj->Body->bpPayRequestResponse->return ?? '');
        $res = explode(',', $result);
        $code = $res[0];

        if ($code !== '0') {
            throw new PaymentException("Mellat Initialization Error: $code");
        }

        $refId = $res[1];

        return [
            'url' => 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat',
            'token' => $refId,
        ];
    }

    public function verify(PaymentVerifyDTO $dto): array
    {
        $orderId = $dto->metadata['order_id'] ?? null;
        $saleOrderId = $dto->metadata['sale_order_id'] ?? null;
        $saleReferenceId = $dto->authority;

        // 1. Verify
        $verifyData = [
            'terminalId' => $this->config['terminal_id'],
            'userName' => $this->config['username'],
            'userPassword' => $this->config['password'],
            'orderId' => $orderId,
            'saleOrderId' => $saleOrderId,
            'saleReferenceId' => $saleReferenceId,
        ];

        $verifyBody = XmlBuilder::build('bpVerifyRequest', $verifyData, [], ['xmlns' => 'http://interfaces.core.sw.bps.com/']);
        $verifyXml = XmlBuilder::soapEnvelope($verifyBody);

        $request = GatewayRequest::post('https://bpm.shaparak.ir/pgwchannel/services/pgw', $verifyXml)
            ->asSoap();

        $response = $this->sender->send($request);
        $this->rawResponse = $response->rawBody;

        if (!$response->success) {
            throw new PaymentException("Mellat Verify Connection Error: " . $response->rawBody);
        }

        $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:', 'ns1:'], '', $response->rawBody);
        $xmlObj = simplexml_load_string($cleanXml);

        $result = (string)($xmlObj->Body->bpVerifyRequestResponse->return ?? '');

        if ($result !== '0') {
            throw new PaymentException("Mellat Verify Error: $result");
        }

        // 2. Settle
        $settleData = [
            'terminalId' => $this->config['terminal_id'],
            'userName' => $this->config['username'],
            'userPassword' => $this->config['password'],
            'orderId' => $orderId,
            'saleOrderId' => $saleOrderId,
            'saleReferenceId' => $saleReferenceId,
        ];

        $settleBody = XmlBuilder::build('bpSettleRequest', $settleData, [], ['xmlns' => 'http://interfaces.core.sw.bps.com/']);
        $settleXml = XmlBuilder::soapEnvelope($settleBody);

        $settleRequest = GatewayRequest::post('https://bpm.shaparak.ir/pgwchannel/services/pgw', $settleXml)
            ->asSoap();

        $responseSettle = $this->sender->send($settleRequest);

        $cleanXmlSettle = str_ireplace(['soap:', 's:', 'xmlns:', 'ns1:'], '', $responseSettle->rawBody);
        $xmlObjSettle = simplexml_load_string($cleanXmlSettle);

        $settleResult = (string)($xmlObjSettle->Body->bpSettleRequestResponse->return ?? '');

        if ($settleResult !== '0' && $settleResult !== '45') {
            throw new PaymentException("Mellat Settle Error: $settleResult");
        }

        return [
            'status' => 'success',
            'ref_id' => $saleReferenceId,
            'tracking_code' => $saleReferenceId,
        ];
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
        // Return array if possible, or null if raw string
        return ['body' => $this->rawResponse];
    }
}
