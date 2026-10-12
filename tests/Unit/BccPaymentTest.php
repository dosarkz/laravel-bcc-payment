<?php

use Dosarkz\BccPayment\BccPayment;
use Dosarkz\BccPayment\Exceptions\BccPaymentException;

function fixedPayment(): BccPayment
{
    return (new BccPayment(20261001120000123, 9000))
        ->description('Registration 10 km')
        ->clientIp('127.0.0.1')
        ->nonce('abcdef0123456789abcdef0123456789')
        ->merchRnId('MERCHRNID0000001')
        ->timestamp(new DateTimeImmutable('2026-10-01 12:00:00', new DateTimeZone('UTC')));
}

describe('params', function () {
    it('builds all signed request fields', function () {
        expect(fixedPayment()->params())->toBe([
            'AMOUNT' => '9000.00',
            'CURRENCY' => '398',
            'ORDER' => '20261001120000123',
            'MERCH_RN_ID' => 'MERCHRNID0000001',
            'DESC' => 'Registration 10 km',
            'MERCHANT' => '88888881',
            'MERCH_NAME' => 'Test Shop',
            'TERMINAL' => '88888881',
            'TIMESTAMP' => '20261001120000',
            'MERCH_GMT' => '0',
            'TRTYPE' => '1',
            'BACKREF' => 'https://example.com/bcc/postpay',
            'LANG' => 'ru',
            'NONCE' => 'abcdef0123456789abcdef0123456789',
            'P_SIGN' => fixedPayment()->params()['P_SIGN'],
            'MK_TOKEN' => 'MERCH',
            'NOTIFY_URL' => 'https://example.com/bcc/notify',
            'CLIENT_IP' => '127.0.0.1',
            'M_INFO' => 'eyJ9',
        ]);
    });

    it('signs the request with HMAC-SHA1 over length-prefixed fields', function () {
        // "length + value" of AMOUNT, CURRENCY, ORDER, MERCHANT, TERMINAL, MERCH_GMT, TIMESTAMP, TRTYPE, NONCE
        $signed = '79000.00'.'3398'.'1720261001120000123'.'888888881'.'888888881'.'10'
            .'1420261001120000'.'11'.'32abcdef0123456789abcdef0123456789';

        expect(fixedPayment()->params()['P_SIGN'])
            ->toBe(hash_hmac('sha1', $signed, hex2bin('00112233445566778899AABBCCDDEEFF')));
    });

    it('formats the amount with two decimals', function (int|float|string $amount, string $expected) {
        expect(BccPayment::make(1, $amount)->params()['AMOUNT'])->toBe($expected);
    })->with([
        'integer' => [500, '500.00'],
        'float' => [1234.5, '1234.50'],
        'string' => ['99.999', '100.00'],
        'large' => [1500000, '1500000.00'],
    ]);

    it('uses a default description', function () {
        expect(BccPayment::make(1000001, 500)->params()['DESC'])->toBe('Order 1000001');
    });

    it('converts the timestamp to UTC', function () {
        $params = BccPayment::make(1, 500)
            ->timestamp(new DateTimeImmutable('2026-10-01 17:00:00', new DateTimeZone('Asia/Almaty')))
            ->params();

        expect($params['TIMESTAMP'])->toBe('20261001120000');
    });

    it('generates a random nonce and merchant reference id', function () {
        $first = BccPayment::make(1, 500)->params();
        $second = BccPayment::make(1, 500)->params();

        expect($first['NONCE'])->toMatch('/^[0-9a-f]{32}$/')
            ->not->toBe($second['NONCE'])
            ->and($first['MERCH_RN_ID'])->toMatch('/^[A-Za-z0-9]{16}$/')
            ->not->toBe($second['MERCH_RN_ID']);
    });

    it('is generated once per instance', function () {
        $payment = BccPayment::make(1000001, 500);

        expect($payment->params())->toBe($payment->params());
    });

    it('is regenerated after a setter is called', function () {
        $payment = BccPayment::make(1000001, 500);
        $nonce = $payment->params()['NONCE'];

        $payment->description('Changed');

        expect($payment->params())
            ->DESC->toBe('Changed')
            ->NONCE->not->toBe($nonce);
    });

    it('omits empty fields', function () {
        config()->set('bcc-payment.notify_url', null);
        config()->set('bcc-payment.merch_name', '');

        expect(BccPayment::make(1, 500)->params())
            ->not->toHaveKeys(['CLIENT_IP', 'NOTIFY_URL', 'MERCH_NAME']);
    });

    it('falls back to KZT and russian when currency and lang are not configured', function () {
        config()->set('bcc-payment.currency', null);
        config()->set('bcc-payment.lang', null);

        expect(BccPayment::make(1, 500)->params())
            ->CURRENCY->toBe('398')
            ->LANG->toBe('ru');
    });
});

describe('setters', function () {
    it('overrides lang, backref and notify url per payment', function () {
        $params = BccPayment::make(1, 500)
            ->lang('en')
            ->backref('https://example.com/custom/back')
            ->notifyUrl('https://example.com/custom/notify')
            ->params();

        expect($params)
            ->LANG->toBe('en')
            ->BACKREF->toBe('https://example.com/custom/back')
            ->NOTIFY_URL->toBe('https://example.com/custom/notify');
    });

    it('accepts config overrides in the constructor', function () {
        $params = (new BccPayment(1, 500, ['lang' => 'kk', 'terminal' => '12345678']))->params();

        expect($params)
            ->LANG->toBe('kk')
            ->TERMINAL->toBe('12345678')
            ->MERCHANT->toBe('88888881');
    });

    it('creates the same payment with make()', function () {
        expect(BccPayment::make(1, 500))->toBeInstanceOf(BccPayment::class)
            ->and(BccPayment::make(1, 500, ['lang' => 'en'])->params()['LANG'])->toBe('en');
    });
});

describe('urls', function () {
    it('returns the gateway url', function () {
        expect(BccPayment::make(1, 500)->url())->toBe('https://test3ds.bcc.kz:5445/cgi-bin/cgi_link');
    });

    it('generates a payment link with the signed fields in the query string', function () {
        $payment = fixedPayment();

        expect($payment->generateUrl())
            ->toBe('https://test3ds.bcc.kz:5445/cgi-bin/cgi_link?'.http_build_query($payment->params()));
    });

    it('appends to an existing query string', function () {
        $url = BccPayment::make(1, 500, ['url' => 'https://bank.test/pay?foo=bar'])->generateUrl();

        expect($url)->toStartWith('https://bank.test/pay?foo=bar&AMOUNT=500.00');
    });
});

describe('redirect', function () {
    it('renders an auto-submitting form with hidden fields', function () {
        $payment = fixedPayment();
        $html = $payment->redirect()->render();

        expect($html)
            ->toContain('id="bccPaymentForm" action="https://test3ds.bcc.kz:5445/cgi-bin/cgi_link" method="POST"')
            ->toContain('document.getElementById(\'bccPaymentForm\').submit()');

        foreach ($payment->params() as $key => $value) {
            expect($html)->toContain('name="'.$key.'" value="'.e($value).'"');
        }
    });

    it('renders a custom view', function () {
        view()->addNamespace('fixtures', __DIR__.'/../fixtures');

        $html = fixedPayment()->redirect('fixtures::custom-redirect')->render();

        expect(trim($html))->toBe('custom:https://test3ds.bcc.kz:5445/cgi-bin/cgi_link:20261001120000123');
    });
});

describe('errors', function () {
    it('throws when a required config value is missing', function (string $key) {
        config()->set("bcc-payment.{$key}", null);

        BccPayment::make(1, 500)->generateUrl();
    })->throws(BccPaymentException::class)->with(['url', 'merchant', 'terminal', 'secret']);

    it('names the missing config key in the message', function () {
        config()->set('bcc-payment.secret', '');

        BccPayment::make(1, 500)->params();
    })->throws(BccPaymentException::class, 'BCC payment config [bcc-payment.secret] is not set.');
});

describe('isSuccessfulResponse', function () {
    it('detects approved transactions', function (array $response, bool $expected) {
        expect(BccPayment::isSuccessfulResponse($response))->toBe($expected);
    })->with([
        'approved' => [['ACTION' => '0', 'RC' => '00'], true],
        'approved with integer action' => [['ACTION' => 0, 'RC' => '00'], true],
        'declined' => [['ACTION' => '2', 'RC' => '05'], false],
        'action ok, rc failed' => [['ACTION' => '0', 'RC' => '51'], false],
        'rc ok, action failed' => [['ACTION' => '3', 'RC' => '00'], false],
        'missing fields' => [[], false],
    ]);
});
