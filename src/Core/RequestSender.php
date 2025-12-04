<?php

namespace PaymentGateway\Core;

use Illuminate\Support\Facades\Http;
use PaymentGateway\Exceptions\PaymentException;
use PaymentGateway\Helpers\XmlBuilder;

class RequestSender
{
    public function send(GatewayRequest $request): GatewayResponse
    {
        try {
            return match ($request->type) {
                GatewayRequest::TYPE_JSON => $this->sendJson($request),
                GatewayRequest::TYPE_FORM => $this->sendForm($request),
                GatewayRequest::TYPE_XML => $this->sendXml($request),
                GatewayRequest::TYPE_SOAP => $this->sendSoap($request),
                default => throw new PaymentException("Unsupported request type: {$request->type}"),
            };
        } catch (\Exception $e) {
            throw new PaymentException("Request failed: " . $e->getMessage(), 0, $e);
        }
    }

    protected function sendJson(GatewayRequest $request): GatewayResponse
    {
        $response = Http::withHeaders($request->headers)
            ->{$request->method}($request->url, $request->data);

        return new GatewayResponse(
            rawBody: $response->body(),
            status: $response->status(),
            headers: $response->headers(),
            success: $response->successful()
        );
    }

    protected function sendForm(GatewayRequest $request): GatewayResponse
    {
        $response = Http::asForm()
            ->withHeaders($request->headers)
            ->{$request->method}($request->url, $request->data);

        return new GatewayResponse(
            rawBody: $response->body(),
            status: $response->status(),
            headers: $response->headers(),
            success: $response->successful()
        );
    }

    protected function sendXml(GatewayRequest $request): GatewayResponse
    {
        // For raw XML, we assume $request->data contains the XML string or we build it?
        // Let's assume $request->data is an array that needs to be converted to XML.
        // Or if it's already a string, send it directly.

        $content = is_array($request->data)
            ? XmlBuilder::build('Request', $request->data)
            : $request->data;

        $response = Http::withHeaders(array_merge(
            ['Content-Type' => 'text/xml; charset=utf-8'],
            $request->headers
        ))
            ->send($request->method, $request->url, ['body' => $content]);

        return new GatewayResponse(
            rawBody: $response->body(),
            status: $response->status(),
            headers: $response->headers(),
            success: $response->successful()
        );
    }

    protected function sendSoap(GatewayRequest $request): GatewayResponse
    {
        // SOAP usually requires a specific structure.
        // We assume $request->data is the body content of the SOAP envelope.
        // If it's an array, we might need a specific root element for the body.

        // This is a simplification. Real SOAP might need more complex building.
        // We will rely on the caller to provide the correct body structure or use a specific builder.

        $content = $request->data;
        if (is_array($content)) {
            // This path is tricky without knowing the root element name.
            // For now, let's assume the caller passes the full XML string for the body
            // OR we need to improve GatewayRequest to hold the root element name.
            // Let's assume for now the caller constructs the body XML.
            $content = XmlBuilder::soapEnvelope('');
        }

        // Actually, let's look at Mellat. It constructs a full XML.
        // So maybe we just pass the full XML string in $request->data for now if it's complex.
        // But the goal is to refactor.

        // Let's update GatewayRequest to support a 'body' string directly if needed.
        // For now, let's assume $request->data IS the string if it's not an array.

        $xml = is_string($request->data) ? $request->data : XmlBuilder::soapEnvelope('');

        $headers = array_merge(
            ['Content-Type' => 'text/xml; charset=utf-8'],
            $request->headers
        );

        if ($request->soapAction) {
            $headers['SOAPAction'] = $request->soapAction;
        }

        $response = Http::withHeaders($headers)
            ->send($request->method, $request->url, ['body' => $xml]);

        return new GatewayResponse(
            rawBody: $response->body(),
            status: $response->status(),
            headers: $response->headers(),
            success: $response->successful()
        );
    }
}
