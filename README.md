# Laravel BCC Payment

Bank CenterCredit (BCC, Kazakhstan) e-commerce payment link generator for Laravel.

The package builds a signed purchase request (`TRTYPE=1`) for the BCC e-commerce gateway, gives you a ready payment URL or an auto-submitting redirect page, and helps you check the bank's response.

Official BCC API documentation: <https://documenter.getpostman.com/view/23274245/2s7YYo95nr#4f91e3c4-dfd5-4c38-a8ea-f08b636f14a6>

## Features

- Builds all request fields required by the gateway (`AMOUNT`, `ORDER`, `NONCE`, `TIMESTAMP`, `MERCH_RN_ID`, etc.).
- Signs the request with `P_SIGN` (HMAC-SHA1 with your HEX MAC key).
- Generates a GET payment link, or an intermediate page that POSTs the signed form to the bank (recommended by BCC).
- Checks callback / `BACKREF` payloads for an approved transaction.
- Fluent API, per-instance config overrides, publishable config and view.

## Requirements

- PHP 8.3+
- Laravel 11, 12 or 13

## Installation

```bash
composer require dosarkz/laravel-bcc-payment
```

The service provider is registered automatically via package discovery.

### Local path repository

To use the package from a local folder (e.g. `packages/bcc-payment`), add it to the application's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/bcc-payment"
    }
],
"require": {
    "dosarkz/laravel-bcc-payment": "@dev"
}
```

Then run `composer update dosarkz/laravel-bcc-payment`.

## Configuration

Add your credentials issued by BCC to `.env`:

```dotenv
BCC_REQUEST_URL=
BCC_MERCHANT=
BCC_MERCH_NAME=""
BCC_TERMINAL=
BCC_SECRET=
BCC_BACKREF=https://your-site.kz/payment/result
BCC_NOTIFY_URL=https://your-site.kz/payment/notify
BCC_CURRENCY=398
BCC_LANG=ru
```

| Variable          | Config key   | Description                                                                 |
|-------------------|--------------|-----------------------------------------------------------------------------|
| `BCC_REQUEST_URL` | `url`        | Gateway endpoint the payment form is sent to (test or production).          |
| `BCC_MERCHANT`    | `merchant`   | Merchant ID issued by the bank. **Required.**                               |
| `BCC_MERCH_NAME`  | `merch_name` | Merchant name shown on the bank page.                                       |
| `BCC_TERMINAL`    | `terminal`   | Terminal ID issued by the bank. **Required.**                               |
| `BCC_SECRET`      | `secret`     | HEX-encoded MAC key used to sign requests (`P_SIGN`). **Required.**         |
| `BCC_BACKREF`     | `backref`    | URL the customer is returned to after payment.                              |
| `BCC_NOTIFY_URL`  | `notify_url` | URL the bank sends the server-to-server notification to.                    |
| `BCC_CURRENCY`    | `currency`   | ISO 4217 numeric currency code. Default `398` (KZT).                        |
| `BCC_LANG`        | `lang`       | Payment page language (`ru`, `kk`, `en`). Default `ru`.                     |
| `BCC_M_INFO`      | `m_info`     | Base64 JSON with 3-D Secure browser/phone info, sent as `M_INFO`.           |

> Use the test or production gateway endpoint provided by the bank.

Publish the config file if you want to change it:

```bash
php artisan vendor:publish --tag=bcc-payment-config
```

## Usage

### Create a payment

```php
use Dosarkz\BccPayment\BccPayment;

$payment = BccPayment::make($order->id, $order->price)
    ->description('Order #'.$order->id)
    ->clientIp($request->ip());
```

The amount is formatted to two decimals (`9000` → `9000.00`). The order ID must be unique per payment attempt on the bank side.

### Redirect the customer to the bank (recommended)

Returns a view that auto-submits the signed form to the gateway with `POST`:

```php
public function pay(Order $order, Request $request): View
{
    return BccPayment::make($order->id, $order->price)
        ->description('Order #'.$order->id)
        ->clientIp($request->ip())
        ->redirect();
}
```

### Get a payment link

```php
$url = BccPayment::make($order->id, $order->price)->generateUrl();

return redirect()->away($url);
```

### Get the signed fields

Use `params()` to build your own form or store the request (for example, to verify `NONCE` on callback). Fields are generated once per instance, so `NONCE` and `TIMESTAMP` stay stable between `params()`, `generateUrl()` and `redirect()` calls.

```php
$payment = BccPayment::make($order->id, $order->price);

$order->update(['bcc_nonce' => $payment->params()['NONCE']]);

return $payment->redirect();
```

Example result:

```php
[
    'AMOUNT' => '9000.00',
    'CURRENCY' => '398',
    'ORDER' => '1000001',
    'MERCH_RN_ID' => 'aB3dE5fG7hJ9kL1m',
    'DESC' => 'Order #1000001',
    'MERCHANT' => '...',
    'MERCH_NAME' => '...',
    'TERMINAL' => '...',
    'TIMESTAMP' => '20261012120000',
    'MERCH_GMT' => '0',
    'TRTYPE' => '1',
    'BACKREF' => 'https://your-site.kz/payment/result',
    'LANG' => 'ru',
    'NONCE' => '...',
    'P_SIGN' => '...',
    'MK_TOKEN' => 'MERCH',
    'NOTIFY_URL' => 'https://your-site.kz/payment/notify',
    'CLIENT_IP' => '127.0.0.1',
    'M_INFO' => '...',
]
```

Empty fields are omitted.

### Available methods

| Method                                   | Description                                                      |
|------------------------------------------|------------------------------------------------------------------|
| `new BccPayment($orderId, $amount, $config = null)` / `BccPayment::make(...)` | Create a payment. `$config` overrides `config('bcc-payment')`. |
| `description(string $description)`       | Payment description (`DESC`). Default: `Order {orderId}`.        |
| `clientIp(?string $ip)`                  | Customer IP (`CLIENT_IP`).                                       |
| `lang(string $lang)`                     | Payment page language (`LANG`).                                  |
| `backref(string $url)`                   | Return URL for this payment (`BACKREF`).                         |
| `notifyUrl(string $url)`                 | Notification URL for this payment (`NOTIFY_URL`).                |
| `nonce(string $nonce)`                   | Custom `NONCE`. Default: random 32-char hex.                     |
| `merchRnId(string $id)`                  | Custom `MERCH_RN_ID`. Default: random 16-char string.            |
| `timestamp(DateTimeInterface $time)`     | Custom `TIMESTAMP` (converted to UTC). Default: now.             |
| `url(): string`                          | Gateway endpoint.                                                |
| `params(): array`                        | Signed request fields.                                           |
| `generateUrl(): string`                  | Payment link with fields in the query string.                    |
| `redirect(string $view = 'bcc-payment::redirect'): View` | Auto-submitting POST form to the bank page.      |
| `BccPayment::isSuccessfulResponse(array $response): bool` | `true` when `ACTION = 0` and `RC = 00`.         |

Calling any setter resets generated fields, so the next `params()` call produces a new signature.

### Per-payment config overrides

```php
$payment = new BccPayment($order->id, $order->price, [
    'lang' => 'en',
    'terminal' => '88888881',
]);
```

### Handle the bank response

The bank returns the customer to `BACKREF` and sends a notification to `NOTIFY_URL` with the transaction result. Use `isSuccessfulResponse()` to check it:

```php
use Dosarkz\BccPayment\BccPayment;

public function notify(Request $request): Response
{
    $order = Order::findOrFail($request->input('ORDER'));

    if (! BccPayment::isSuccessfulResponse($request->all())) {
        $order->markAsFailed();

        return response('FAILED');
    }

    $order->markAsPaid();

    return response('OK');
}
```

Exclude the notification route from CSRF verification, since the bank posts to it directly:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['payment/notify']);
})
```

See the [official documentation](https://documenter.getpostman.com/view/23274245/2s7YYo95nr#4f91e3c4-dfd5-4c38-a8ea-f08b636f14a6) for the full list of response fields and `RC` codes.

### Signature

`P_SIGN` is an HMAC-SHA1 over the concatenation of `length + value` of these fields, keyed by the HEX-decoded `secret`:

```
AMOUNT, CURRENCY, ORDER, MERCHANT, TERMINAL, MERCH_GMT, TIMESTAMP, TRTYPE, NONCE
```

## Customizing the redirect page

Publish the view:

```bash
php artisan vendor:publish --tag=bcc-payment-views
```

It will be copied to `resources/views/vendor/bcc-payment/redirect.blade.php`. Or pass your own view, which receives `$url` and `$params`:

```php
return $payment->redirect('payments.bcc-redirect');
```

## Errors

A `Dosarkz\BccPayment\Exceptions\BccPaymentException` is thrown when a required config value (`url`, `merchant`, `terminal`, `secret`) is missing:

```
BCC payment config [bcc-payment.secret] is not set.
```

## Testing

```bash
composer install
composer test
```

## License

MIT
