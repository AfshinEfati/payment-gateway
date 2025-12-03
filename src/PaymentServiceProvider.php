<?php

namespace PaymentGateway;

use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/payment.php',
            'payment'
        );

        $this->app->bind('payment.zarinpal', function ($app) {
            return new \PaymentGateway\Gateways\Zarinpal($app['config']['payment.gateways.zarinpal']);
        });

        $this->app->bind('payment.mellat', function ($app) {
            return new \PaymentGateway\Gateways\Mellat($app['config']['payment.gateways.mellat']);
        });

        $this->app->singleton('payment', function ($app) {
            return new \PaymentGateway\Managers\PaymentManager($app);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/payment.php' => config_path('payment.php'),
        ], 'payment-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'payment-migrations');

        $this->publishes([
            __DIR__ . '/../database/seeders/' => database_path('seeders'),
        ], 'payment-seeders');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
