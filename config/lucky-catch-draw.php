<?php

return [
    'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
    'admin_password' => env('ADMIN_PASSWORD', 'password'),

    /*
    |--------------------------------------------------------------------------
    | Normalization
    |--------------------------------------------------------------------------
    |
    | Tracking query parameters stripped when normalizing a profile URL for
    | duplicate detection. The raw, original URL is always kept untouched.
    |
    */
    'tracking_params' => [
        'fbclid',
        'igshid',
        'si',
        'ref',
    ],

    'tracking_param_prefixes' => [
        'utm_',
    ],
];
