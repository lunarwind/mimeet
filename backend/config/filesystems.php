<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app'), 'throw' => false],
        'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => env('APP_URL').'/storage', 'visibility' => 'public', 'throw' => false],
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
        ],
        'quarantine' => [
            'driver' => 'local',
            'root' => storage_path('app/quarantine'),
            'visibility' => 'private',
        ],
    ],
    'links' => [public_path('storage') => storage_path('app/public')],
];
