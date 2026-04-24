<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(
        array_map(
            'trim',
            explode(',', env('CORS_ALLOWED_ORIGINS', 'https://mimeet.online,https://www.mimeet.online,https://admin.mimeet.online'))
        )
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
