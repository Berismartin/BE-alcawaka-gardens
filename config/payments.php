<?php

return [
    // Minutes a website booking holds its room while the guest pays.
    'hold_minutes' => (int) env('PAYMENT_HOLD_MINUTES', 10),

    // shortcut: no gateway is connected yet; online payments wait for the test simulator on the
    // booking page. Turn this off and plug MTN, Airtel and the card gateway in when the merchant keys arrive.
    'simulator' => (bool) env('PAYMENT_SIMULATOR', env('APP_ENV') !== 'production'),
];
