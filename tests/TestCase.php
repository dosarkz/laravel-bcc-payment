<?php

namespace Dosarkz\BccPayment\Tests;

use Dosarkz\BccPayment\BccPaymentServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [BccPaymentServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('bcc-payment', [
            'url' => 'https://test3ds.bcc.kz:5445/cgi-bin/cgi_link',
            'merchant' => '88888881',
            'merch_name' => 'Test Shop',
            'terminal' => '88888881',
            'secret' => '00112233445566778899AABBCCDDEEFF',
            'backref' => 'https://example.com/bcc/postpay',
            'notify_url' => 'https://example.com/bcc/notify',
            'currency' => '398',
            'lang' => 'ru',
            'm_info' => 'eyJ9',
        ]);
    }
}
