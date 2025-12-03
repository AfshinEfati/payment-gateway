<?php

namespace PaymentGateway\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \PaymentGateway\Contracts\PaymentGatewayInterface gateway(string|null $name = null)
 * @method static array initialize(\PaymentGateway\DTOs\PaymentRequestDTO $dto)
 * @method static array verify(\PaymentGateway\DTOs\PaymentVerifyDTO $dto)
 *
 * @see \PaymentGateway\Managers\PaymentManager
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'payment';
    }
}
