<?php

namespace App\Payment\DTOs;

class PaymentVerifyDTO
{
    public function __construct(
        public readonly int $amount,
        public readonly string $authority,
        public readonly ?string $gateway = null,
        public readonly array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'authority' => $this->authority,
            'gateway' => $this->gateway,
            'metadata' => $this->metadata,
        ];
    }
}
