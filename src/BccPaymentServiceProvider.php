<?php

namespace Dosarkz\BccPayment;

use Illuminate\Support\ServiceProvider;

class BccPaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bcc-payment.php', 'bcc-payment');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bcc-payment');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/bcc-payment.php' => config_path('bcc-payment.php'),
            ], 'bcc-payment-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/bcc-payment'),
            ], 'bcc-payment-views');
        }
    }
}
