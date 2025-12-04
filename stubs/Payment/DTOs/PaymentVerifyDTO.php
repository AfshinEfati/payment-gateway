<?php

namespace App\Payment\DTOs;

readonly class PaymentVerifyDTO
{
    public function __construct(
        public int $amount,
        public string $authority,
        public ?string $gateway = null,
        public array $metadata = [],
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
