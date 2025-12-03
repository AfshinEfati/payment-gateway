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

        // Register Command
        if ($this->app->runningInConsole()) {
            $this->commands([
                \PaymentGateway\Console\Commands\InstallPackage::class,
            ]);
        }

        // Bind Gateways
        $this->app->bind('payment.zarinpal', function ($app) {
            if (class_exists(\App\Payment\Gateways\Zarinpal::class)) {
                return new \App\Payment\Gateways\Zarinpal($app['config']['payment.gateways.zarinpal']);
            }
            return new \PaymentGateway\Gateways\Zarinpal($app['config']['payment.gateways.zarinpal']);
        });

        $this->app->bind('payment.mellat', function ($app) {
            if (class_exists(\App\Payment\Gateways\Mellat::class)) {
                return new \App\Payment\Gateways\Mellat($app['config']['payment.gateways.mellat']);
            }
            return new \PaymentGateway\Gateways\Mellat($app['config']['payment.gateways.mellat']);
        });

        // Bind Manager
        $this->app->singleton('payment', function ($app) {
            if (class_exists(\App\Payment\PaymentManager::class)) {
                return new \App\Payment\PaymentManager($app);
            }
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
        ], 'efati-payment-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'efati-payment-migrations');

        $this->publishes([
            __DIR__ . '/../database/seeders/' => database_path('seeders'),
        ], 'efati-payment-seeders');

        $this->publishes([
            __DIR__ . '/Models' => app_path('Models'),
        ], 'efati-payment-models');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
