<?php

namespace App\Payment\Gateways;

use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\Core\GatewayRequest;
use App\Payment\Core\RequestSender;
use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;
use App\Payment\PaymentException;
use App\Payment\Helpers\XmlBuilder;

class Parsian implements PaymentGatewayInterface
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
    $url = 'https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?op=SalePaymentRequest';

    $data = [
      'SalePaymentRequest' => [
        'requestData' => [
          'LoginAccount' => $this->config['pin'],
          'Amount' => $dto->amount,
          'OrderId' => $dto->orderId,
          'CallBackUrl' => $dto->callbackUrl,
          'AdditionalData' => '',
          'Originator' => '',
        ]
      ]
    ];

    $body = XmlBuilder::build('SalePaymentRequest', $data['SalePaymentRequest'], [], ['xmlns' => 'https://pec.Shaparak.ir/NewIPGServices/Sale/SaleService']);
    $xml = XmlBuilder::soapEnvelope($body);

    $request = GatewayRequest::post($url, $xml)
      ->asSoap('https://pec.Shaparak.ir/NewIPGServices/Sale/SaleService/SalePaymentRequest');

    $response = $this->sender->send($request);

    // Parse SOAP response
    $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $response->rawBody);
    $xmlObj = simplexml_load_string($cleanXml);

    $result = $xmlObj->Body->SalePaymentRequestResponse->SalePaymentRequestResult ?? null;

    $token = (string)$result->Token ?? null;
    $status = (int)$result->Status ?? -1;

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

    $data = [
      'ConfirmPayment' => [
        'requestData' => [
          'LoginAccount' => $this->config['pin'],
          'Token' => $dto->authority,
        ]
      ]
    ];

    $body = XmlBuilder::build('ConfirmPayment', $data['ConfirmPayment'], [], ['xmlns' => 'https://pec.Shaparak.ir/NewIPGServices/Confirm/ConfirmService']);
    $xml = XmlBuilder::soapEnvelope($body);

    $request = GatewayRequest::post($url, $xml)
      ->asSoap('https://pec.Shaparak.ir/NewIPGServices/Confirm/ConfirmService/ConfirmPayment');

    $response = $this->sender->send($request);

    // Parse SOAP response
    $cleanXml = str_ireplace(['soap:', 's:', 'xmlns:'], '', $response->rawBody);
    $xmlObj = simplexml_load_string($cleanXml);

    $result = $xmlObj->Body->ConfirmPaymentResponse->ConfirmPaymentResult ?? null;

    $status = (int)$result->Status ?? -1;
    $rrn = (string)$result->RRN ?? null;

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
