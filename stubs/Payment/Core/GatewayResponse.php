<?php

namespace App\Payment\Core;

class GatewayResponse
{
    public function __construct(
        public string $rawBody,
        public int $status,
        public array $headers = [],
        public ?array $data = null,
        public bool $success = true,
        public ?string $message = null
    ) {
        if ($this->data === null) {
            $this->data = $this->parseJson($rawBody);
        }
    }

    protected function parseJson(string $body): array
    {
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isSuccess(): bool
    {
        return $this->success && $this->status >= 200 && $this->status < 300;
    }

    public function get(string $key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }
}
