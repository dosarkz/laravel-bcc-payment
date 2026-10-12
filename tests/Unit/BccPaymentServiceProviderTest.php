<?php

use Dosarkz\BccPayment\BccPaymentServiceProvider;
use Illuminate\Support\ServiceProvider;

it('registers the redirect view', function () {
    expect(view()->exists('bcc-payment::redirect'))->toBeTrue();
});

it('merges the package config with defaults', function () {
    config()->set('bcc-payment', []);

    (new BccPaymentServiceProvider(app()))->register();

    expect(config('bcc-payment'))
        ->toHaveKeys(['url', 'merchant', 'merch_name', 'terminal', 'secret', 'backref', 'notify_url', 'currency', 'lang', 'm_info'])
        ->currency->toBe('398')
        ->lang->toBe('ru');
});

it('publishes the config and views', function () {
    expect(ServiceProvider::pathsToPublish(BccPaymentServiceProvider::class, 'bcc-payment-config'))
        ->toHaveCount(1)
        ->toContain(config_path('bcc-payment.php'))
        ->and(ServiceProvider::pathsToPublish(BccPaymentServiceProvider::class, 'bcc-payment-views'))
        ->toContain(resource_path('views/vendor/bcc-payment'));
});
