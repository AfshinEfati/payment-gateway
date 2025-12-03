<?php

namespace App\Payment\Contracts;

use App\Payment\DTOs\PaymentRequestDTO;
use App\Payment\DTOs\PaymentVerifyDTO;

interface PaymentGatewayInterface
{
    /**
     * Initialize the payment request.
     *
     * @param PaymentRequestDTO $dto
     * @return array ['url' => string, 'token' => string|null]
     */
    public function initialize(PaymentRequestDTO $dto): array;

    /**
     * Verify the payment transaction.
     *
     * @param PaymentVerifyDTO $dto
     * @return array ['status' => string, 'ref_id' => string, 'tracking_code' => string]
     */
    public function verify(PaymentVerifyDTO $dto): array;

    /**
     * Get the transaction ID (Authority/Token).
     *
     * @return string|null
     */
    public function getTransactionId(): ?string;

    /**
     * Get the tracking code (RefID).
     *
     * @return string|null
     */
    public function getTrackingCode(): ?string;

    /**
     * Get the raw response from the gateway.
     *
     * @return array|null
     */
    public function getRawResponse(): ?array;
}
