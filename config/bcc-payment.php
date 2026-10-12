<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bank CenterCredit e-commerce gateway
    |--------------------------------------------------------------------------
    |
    | Credentials issued by BCC. "secret" is the HEX-encoded MAC key used to
    | sign requests (P_SIGN, HMAC-SHA1).
    |
    */

    'url' => env('BCC_REQUEST_URL'),
    'merchant' => env('BCC_MERCHANT'),
    'merch_name' => env('BCC_MERCH_NAME'),
    'terminal' => env('BCC_TERMINAL'),
    'secret' => env('BCC_SECRET'),

    /* Where the bank returns the customer and sends the server notification. */
    'backref' => env('BCC_BACKREF'),
    'notify_url' => env('BCC_NOTIFY_URL'),

    /* ISO 4217 numeric code, 398 = KZT. */
    'currency' => env('BCC_CURRENCY', '398'),

    'lang' => env('BCC_LANG', 'ru'),

    /* Base64 JSON with 3-D Secure browser/phone info, sent as M_INFO. */
    'm_info' => env('BCC_M_INFO', 'eyJicm93c2VyU2NyZWVuSGVpZ2h0Ijo4NjcsImJyb3dzZXJTY3JlZW5XaWR0aCI6MTQ2MywibW9iaWxlUGhvbmUiOnsiY2MiOiI3Iiwic3Vic2NyaWJlciI6Ijc0NzU1NTg4ODgifX0='), // this is example from off documentation

];
