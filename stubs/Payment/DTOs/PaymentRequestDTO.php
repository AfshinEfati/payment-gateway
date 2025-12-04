<?php

namespace App\Payment\DTOs;

readonly class PaymentRequestDTO
{
    public function __construct(
        public int $amount,
        public string $orderId,
        public string $callbackUrl,
        public ?string $description = null,
        public ?string $mobile = null,
        public ?string $email = null,
        public array $metadata = [],
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
