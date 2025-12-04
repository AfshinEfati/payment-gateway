<?php

namespace PaymentGateway\Helpers;

class XmlBuilder
{
    public static function build(string $root, array $data, array $attributes = [], array $namespaces = []): string
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>';

        $nsString = '';
        foreach ($namespaces as $prefix => $uri) {
            $nsString .= " xmlns:{$prefix}=\"{$uri}\"";
        }

        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= " {$key}=\"{$value}\"";
        }

        $xml .= "<{$root}{$nsString}{$attrString}>";
        $xml .= self::arrayToXml($data);
        $xml .= "</{$root}>";

        return $xml;
    }

    public static function soapEnvelope(string $bodyContent, array $namespaces = []): string
    {
        $defaultNamespaces = [
            'xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            'xsd' => 'http://www.w3.org/2001/XMLSchema',
            'soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
        ];

        $namespaces = array_merge($defaultNamespaces, $namespaces);

        return self::build('soap:Envelope', ['soap:Body' => $bodyContent], [], $namespaces);
    }

    protected static function arrayToXml($data): string
    {
        $xml = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Handle numeric keys (lists) if necessary, but for SOAP usually keys are named
                if (is_numeric($key)) {
                    foreach ($value as $item) {
                        $xml .= self::arrayToXml($item);
                    }
                } else {
                    $xml .= "<{$key}>";
                    $xml .= self::arrayToXml($value);
                    $xml .= "</{$key}>";
                }
            } else {
                // If the value is raw XML (e.g. pre-built body), don't escape it? 
                // For safety, we usually escape, but if we pass a string that is meant to be a sub-tree...
                // Let's assume values are simple strings for now.
                // If we need to pass raw XML, we might need a special wrapper.
                $xml .= "<{$key}>" . htmlspecialchars((string)$value) . "</{$key}>";
            }
        }
        return $xml;
    }
}
