<?php

namespace App\Payment;

use Exception;

class PaymentException extends Exception
{
    public static function unknownGateway(string $gateway): self
    {
        return new self("Gateway [{$gateway}] is not supported or not registered.");
    }

    public static function connectionError(string $gateway, string $message): self
    {
        return new self("{$gateway} Connection Error: {$message}");
    }
}
