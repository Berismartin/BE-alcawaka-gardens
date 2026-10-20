<?php

$gateway = env('PAYMENT_GATEWAY', 'simulator'); // simulator | pesapal

return [
    'gateway' => $gateway,

    // Minutes a website booking holds its room while the guest pays. Pesapal's hosted page needs longer.
    'hold_minutes' => (int) env('PAYMENT_HOLD_MINUTES', $gateway === 'pesapal' ? 20 : 10),

    // Local development and tests: the booking page's test panel approves or declines payments.
    'simulator' => $gateway === 'simulator',

    // A pending online payment nobody answers for this long is marked failed by `payments:reconcile`.
    'abandon_after_hours' => (int) env('PAYMENT_ABANDON_AFTER_HOURS', 72),

    'pesapal' => [
        // sandbox: https://cybqa.pesapal.com/pesapalv3 · live: https://pay.pesapal.com/v3
        'base_url' => env('PESAPAL_ENV', 'sandbox') === 'live' ? 'https://pay.pesapal.com/v3' : 'https://cybqa.pesapal.com/pesapalv3',
        'consumer_key' => env('PESAPAL_CONSUMER_KEY'),
        'consumer_secret' => env('PESAPAL_CONSUMER_SECRET'),
        // From `php artisan payments:pesapal-register-ipn`
        'ipn_id' => env('PESAPAL_IPN_ID'),
        'currency' => 'UGX',
        'country' => 'UG',
        'timeout' => (int) env('PESAPAL_TIMEOUT', 20),
    ],
];
