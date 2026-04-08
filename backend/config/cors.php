<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:3000', 'http://localhost:3001'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'X-XSRF-TOKEN',
        'X-Requested-With',
        'Authorization',
        'Accept',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
