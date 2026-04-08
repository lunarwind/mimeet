<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__ . '/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        // Force test-friendly config (Docker .env overrides phpunit.xml)
        $app['config']->set('cache.default', 'array');
        $app['config']->set('broadcasting.default', 'log');

        return $app;
    }
}
