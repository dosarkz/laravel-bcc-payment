<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Redirecting to the bank page…') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font: 16px/1.5 Arial, sans-serif; color: #0e1737; background: #f3f6fd; text-align: center; padding: 16px; box-sizing: border-box; }
        .spin { width: 48px; height: 48px; border-radius: 50%; border: 4px solid #e7edfb; border-top-color: #3252c6; margin: 0 auto 20px; animation: s .9s linear infinite; }
        @keyframes s { to { transform: rotate(360deg); } }
        button { margin-top: 16px; min-height: 44px; padding: 0 20px; border-radius: 999px; border: 0; background: #3252c6; color: #fff; font: 600 15px Arial, sans-serif; cursor: pointer; }
    </style>
</head>
<body>
<div>
    <div class="spin"></div>
    <b>{{ __('Redirecting to the bank page…') }}</b>
    <form id="bccPaymentForm" action="{{ $url }}" method="POST">
        @foreach ($params as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <noscript><button type="submit">{{ __('Proceed to payment') }}</button></noscript>
    </form>
</div>
<script>
    document.getElementById('bccPaymentForm').submit();
</script>
</body>
</html>
