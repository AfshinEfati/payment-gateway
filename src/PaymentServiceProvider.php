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

        $this->app->bind('payment.idpay', function ($app) {
            if (class_exists(\App\Payment\Gateways\IDPay::class)) {
                return new \App\Payment\Gateways\IDPay($app['config']['payment.gateways.idpay']);
            }
            return new \PaymentGateway\Gateways\IDPay($app['config']['payment.gateways.idpay']);
        });

        $this->app->bind('payment.irankish', function ($app) {
            if (class_exists(\App\Payment\Gateways\IranKish::class)) {
                return new \App\Payment\Gateways\IranKish($app['config']['payment.gateways.irankish']);
            }
            return new \PaymentGateway\Gateways\IranKish($app['config']['payment.gateways.irankish']);
        });

        $this->app->bind('payment.nextpay', function ($app) {
            if (class_exists(\App\Payment\Gateways\NextPay::class)) {
                return new \App\Payment\Gateways\NextPay($app['config']['payment.gateways.nextpay']);
            }
            return new \PaymentGateway\Gateways\NextPay($app['config']['payment.gateways.nextpay']);
        });

        $this->app->bind('payment.payir', function ($app) {
            if (class_exists(\App\Payment\Gateways\PayIr::class)) {
                return new \App\Payment\Gateways\PayIr($app['config']['payment.gateways.payir']);
            }
            return new \PaymentGateway\Gateways\PayIr($app['config']['payment.gateways.payir']);
        });

        $this->app->bind('payment.parsian', function ($app) {
            if (class_exists(\App\Payment\Gateways\Parsian::class)) {
                return new \App\Payment\Gateways\Parsian($app['config']['payment.gateways.parsian']);
            }
            return new \PaymentGateway\Gateways\Parsian($app['config']['payment.gateways.parsian']);
        });

        $this->app->bind('payment.pasargad', function ($app) {
            if (class_exists(\App\Payment\Gateways\Pasargad::class)) {
                return new \App\Payment\Gateways\Pasargad($app['config']['payment.gateways.pasargad']);
            }
            return new \PaymentGateway\Gateways\Pasargad($app['config']['payment.gateways.pasargad']);
        });

        $this->app->bind('payment.payping', function ($app) {
            if (class_exists(\App\Payment\Gateways\PayPing::class)) {
                return new \App\Payment\Gateways\PayPing($app['config']['payment.gateways.payping']);
            }
            return new \PaymentGateway\Gateways\PayPing($app['config']['payment.gateways.payping']);
        });

        $this->app->bind('payment.sadad', function ($app) {
            if (class_exists(\App\Payment\Gateways\Sadad::class)) {
                return new \App\Payment\Gateways\Sadad($app['config']['payment.gateways.sadad']);
            }
            return new \PaymentGateway\Gateways\Sadad($app['config']['payment.gateways.sadad']);
        });

        $this->app->bind('payment.saman', function ($app) {
            if (class_exists(\App\Payment\Gateways\Saman::class)) {
                return new \App\Payment\Gateways\Saman($app['config']['payment.gateways.saman']);
            }
            return new \PaymentGateway\Gateways\Saman($app['config']['payment.gateways.saman']);
        });

        $this->app->bind('payment.asanpardakht', function ($app) {
            if (class_exists(\App\Payment\Gateways\AsanPardakht::class)) {
                return new \App\Payment\Gateways\AsanPardakht($app['config']['payment.gateways.asanpardakht']);
            }
            return new \PaymentGateway\Gateways\AsanPardakht($app['config']['payment.gateways.asanpardakht']);
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
            __DIR__ . '/../stubs/database/seeders/' => database_path('seeders'),
        ], 'efati-payment-seeders');

        $this->publishes([
            __DIR__ . '/../stubs/Models' => app_path('Models'),
        ], 'efati-payment-models');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
