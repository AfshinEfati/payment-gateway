<?php

namespace App\Payment\Core;

use Illuminate\Support\Facades\Http;
use App\Payment\PaymentException;
use App\Payment\Helpers\XmlBuilder;

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
