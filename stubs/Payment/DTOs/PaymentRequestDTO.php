<?php

namespace App\Payment\DTOs;

class PaymentRequestDTO
{
    public function __construct(
        public readonly int $amount,
        public readonly string $orderId,
        public readonly string $callbackUrl,
        public readonly ?string $description = null,
        public readonly ?string $mobile = null,
        public readonly ?string $email = null,
        public readonly array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'order_id' => $this->orderId,
            'callback_url' => $this->callbackUrl,
            'description' => $this->description,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'metadata' => $this->metadata,
        ];
    }
}
