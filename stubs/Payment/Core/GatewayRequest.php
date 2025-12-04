<?php

namespace App\Payment\Core;

class GatewayRequest
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';

    public const TYPE_JSON = 'JSON';
    public const TYPE_FORM = 'FORM';
    public const TYPE_XML = 'XML';
    public const TYPE_SOAP = 'SOAP';

    public function __construct(
        public string $method,
        public string $url,
        public mixed $data = [],
        public array $headers = [],
        public string $type = self::TYPE_JSON,
        public ?string $soapAction = null,
        public ?string $description = null
    ) {}

    public static function post(string $url, mixed $data = []): self
    {
        return new self(self::METHOD_POST, $url, $data);
    }

    public static function get(string $url, mixed $data = []): self
    {
        return new self(self::METHOD_GET, $url, $data);
    }

    public function asJson(): self
    {
        $this->type = self::TYPE_JSON;
        return $this;
    }

    public function asForm(): self
    {
        $this->type = self::TYPE_FORM;
        return $this;
    }

    public function asXml(): self
    {
        $this->type = self::TYPE_XML;
        return $this;
    }

    public function asSoap(?string $action = null): self
    {
        $this->type = self::TYPE_SOAP;
        $this->soapAction = $action;
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }
}
