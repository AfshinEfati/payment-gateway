<?php

namespace PaymentGateway\Managers;

use Illuminate\Contracts\Foundation\Application;
use PaymentGateway\Contracts\PaymentGatewayInterface;
use PaymentGateway\Exceptions\PaymentException;

class PaymentManager
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get a gateway instance.
     *
     * @param string|null $name
     * @return PaymentGatewayInterface
     * @throws PaymentException
     */
    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: $this->getDefaultGateway();

        // We assume the gateway is bound in the container as 'payment.drivers.{name}'
        // or simply 'payment.{name}' as per the TODO.
        // Let's use a consistent naming convention.
        $binding = "payment.{$name}";

        if (!$this->app->bound($binding)) {
            throw new PaymentException("Gateway [{$name}] is not supported or not registered.");
        }

        return $this->app->make($binding);
    }

    /**
     * Get the default gateway name.
     *
     * @return string
     */
    public function getDefaultGateway(): string
    {
        return $this->app['config']['payment.default'];
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->gateway()->$method(...$parameters);
    }
}
