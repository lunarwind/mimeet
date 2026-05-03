<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        // Force test-friendly env BEFORE bootstrap.
        // 必須在 bootstrap() 之前設定，因為 BroadcastServiceProvider 在
        // boot() 階段就會 resolve default driver；若 BROADCAST_DRIVER 為空
        // 會 fallback 到 'reverb'，再 fallback 到 Pusher SDK，導致 TypeError。
        $testEnv = [
            'APP_ENV'          => 'testing',
            'APP_KEY'          => 'base64:Vghi6lFfFa8dMu3eYrM+PxKRq+u6SRrwbjQs8aXwL7g=', // 測試專用 key
            'BROADCAST_DRIVER' => 'null',
            'CACHE_DRIVER'     => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER'      => 'array',
            'SESSION_DRIVER'   => 'array',
            // PR-Revert: 測試環境改用 MySQL（與 production 一致）。
            // 詳見 docs/decisions/2026-05-03-test-environment-mysql-migration.md
            'DB_CONNECTION'    => 'mysql',
            'DB_HOST'          => 'mysql',  // docker-compose service name; from host machine override to 127.0.0.1
            'DB_PORT'          => '3306',
            'DB_DATABASE'      => 'mimeet_test',
            'DB_USERNAME'      => 'root',
            'DB_PASSWORD'      => 'root_secret_2024',
        ];
        foreach ($testEnv as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $app = require __DIR__ . '/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
