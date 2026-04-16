<?php

return [

    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
        ],

        'resend' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.resend.com'),
            'port' => env('MAIL_PORT', 2587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => 'resend',
            'password' => env('RESEND_API_KEY'),
            'timeout' => null,
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@mimeet.tw'),
        'name' => env('MAIL_FROM_NAME', 'MiMeet'),
    ],

];
