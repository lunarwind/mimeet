<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\CreditScoreObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        User::observe(CreditScoreObserver::class);
    }
}
