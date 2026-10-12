<?php

namespace Dosarkz\BccPayment;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Dosarkz\BccPayment\Exceptions\BccPaymentException;
use Illuminate\Contracts\View\View;

/**
 * Builds a signed Bank CenterCredit e-commerce payment request (TRTYPE=1, purchase).
 *
 * Usage:
 *   $payment = new BccPayment($order->id, $order->price);
 *   $payment->description('Order #'.$order->id)->clientIp($request->ip());
 *   $payment->params();      // signed form fields, store them to check NONCE on callback
 *   $payment->generateUrl(); // GET link to the bank page
 *   return $payment->redirect(); // auto-submitting POST form to the bank page
 */
class BccPayment
{
    public const int TRANSACTION_TYPE_PURCHASE = 1;

    public const string RESPONSE_ACTION_SUCCESS = '0';

    public const string RESPONSE_CODE_SUCCESS = '00';

    private string $orderId;

    private string $amount;

    /** @var array<string, mixed> */
    private array $config;

    private ?string $description = null;

    private ?string $clientIp = null;

    private ?string $nonce = null;

    private ?string $merchRnId = null;

    private ?DateTimeInterface $timestamp = null;

    /** @var array<string, string>|null */
    private ?array $params = null;

    /**
     * @param  array<string, mixed>|null  $config  Overrides for config('bcc-payment').
     */
    public function __construct(int|string $orderId, int|float|string $amount, ?array $config = null)
    {
        $this->orderId = (string) $orderId;
        $this->amount = number_format((float) $amount, 2, '.', '');
        $this->config = array_merge((array) config('bcc-payment', []), $config ?? []);
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    public static function make(int|string $orderId, int|float|string $amount, ?array $config = null): static
    {
        return new static($orderId, $amount, $config);
    }

    public function description(string $description): static
    {
        return $this->set('description', $description);
    }

    public function clientIp(?string $clientIp): static
    {
        return $this->set('clientIp', $clientIp);
    }

    public function lang(string $lang): static
    {
        $this->config['lang'] = $lang;
        $this->params = null;

        return $this;
    }

    public function backref(string $url): static
    {
        $this->config['backref'] = $url;
        $this->params = null;

        return $this;
    }

    public function notifyUrl(string $url): static
    {
        $this->config['notify_url'] = $url;
        $this->params = null;

        return $this;
    }

    public function nonce(string $nonce): static
    {
        return $this->set('nonce', $nonce);
    }

    public function merchRnId(string $merchRnId): static
    {
        return $this->set('merchRnId', $merchRnId);
    }

    public function timestamp(DateTimeInterface $timestamp): static
    {
        return $this->set('timestamp', $timestamp);
    }

    /**
     * Gateway endpoint the form is submitted to.
     */
    public function url(): string
    {
        return (string) $this->requireConfig('url');
    }

    /**
     * Signed request fields. Generated once per instance so NONCE stays stable.
     *
     * @return array<string, string>
     */
    public function params(): array
    {
        return $this->params ??= $this->buildParams();
    }

    /**
     * Payment page link with all signed fields in the query string.
     */
    public function generateUrl(): string
    {
        $url = $this->url();

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($this->params());
    }

    /**
     * Intermediate page that auto-submits the signed form to the bank (recommended by BCC).
     */
    public function redirect(string $view = 'bcc-payment::redirect'): View
    {
        return view($view, [
            'url' => $this->url(),
            'params' => $this->params(),
        ]);
    }

    /**
     * Check the bank callback / BACKREF payload for an approved transaction.
     *
     * @param  array<string, mixed>  $response
     */
    public static function isSuccessfulResponse(array $response): bool
    {
        return (string) ($response['ACTION'] ?? '') === self::RESPONSE_ACTION_SUCCESS
            && (string) ($response['RC'] ?? '') === self::RESPONSE_CODE_SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function buildParams(): array
    {
        $timestamp = DateTimeImmutable::createFromInterface($this->timestamp ?? new DateTimeImmutable)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('YmdHis');
        $nonce = $this->nonce ?? bin2hex(random_bytes(16));
        $merchant = (string) $this->requireConfig('merchant');
        $terminal = (string) $this->requireConfig('terminal');
        $currency = (string) ($this->config['currency'] ?? '398');
        $merchGmt = '0';
        $trType = (string) self::TRANSACTION_TYPE_PURCHASE;

        $params = [
            'AMOUNT' => $this->amount,
            'CURRENCY' => $currency,
            'ORDER' => $this->orderId,
            'MERCH_RN_ID' => $this->merchRnId ?? $this->randomMerchRnId(),
            'DESC' => $this->description ?? 'Order '.$this->orderId,
            'MERCHANT' => $merchant,
            'MERCH_NAME' => (string) ($this->config['merch_name'] ?? ''),
            'TERMINAL' => $terminal,
            'TIMESTAMP' => $timestamp,
            'MERCH_GMT' => $merchGmt,
            'TRTYPE' => $trType,
            'BACKREF' => (string) ($this->config['backref'] ?? ''),
            'LANG' => (string) ($this->config['lang'] ?? 'ru'),
            'NONCE' => $nonce,
            'P_SIGN' => $this->sign([$this->amount, $currency, $this->orderId, $merchant, $terminal, $merchGmt, $timestamp, $trType, $nonce]),
            'MK_TOKEN' => 'MERCH',
            'NOTIFY_URL' => (string) ($this->config['notify_url'] ?? ''),
            'CLIENT_IP' => (string) $this->clientIp,
            'M_INFO' => (string) ($this->config['m_info'] ?? ''),
        ];

        return array_filter($params, fn (string $value): bool => $value !== '');
    }

    /**
     * P_SIGN = HMAC-SHA1 over "length + value" of each signed field, keyed by the HEX-decoded secret.
     *
     * @param  list<string>  $values
     */
    private function sign(array $values): string
    {
        $data = implode('', array_map(fn (string $value): string => strlen($value).$value, $values));

        return hash_hmac('sha1', $data, (string) hex2bin((string) $this->requireConfig('secret')));
    }

    private function randomMerchRnId(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $merchRnId = '';

        for ($i = 0; $i < 16; $i++) {
            $merchRnId .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $merchRnId;
    }

    private function requireConfig(string $key): mixed
    {
        $value = $this->config[$key] ?? null;

        if ($value === null || $value === '') {
            throw BccPaymentException::missingConfig($key);
        }

        return $value;
    }

    private function set(string $property, mixed $value): static
    {
        $this->{$property} = $value;
        $this->params = null;

        return $this;
    }
}
