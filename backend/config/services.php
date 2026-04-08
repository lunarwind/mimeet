<?php

return [
    'ecpay' => [
        'merchant_id' => env('ECPAY_MERCHANT_ID', '3002607'),
        'hash_key' => env('ECPAY_HASH_KEY', 'pwFHCqoQZGmho4w6'),
        'hash_iv' => env('ECPAY_HASH_IV', 'EkRm7iFT261dpevs'),
        'is_sandbox' => (bool) env('ECPAY_IS_SANDBOX', true),
    ],
    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY', ''),
    ],
];
