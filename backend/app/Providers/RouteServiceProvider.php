<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/';

    public function boot(): void
    {
        // General API: 200 req/min per user (supports 200 concurrent users)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(200)->by($request->user()?->id ?: $request->ip());
        });

        // Login: handled in AuthController (email 5 fails + IP 20 fails → 5 min cooldown)

        // Register: 30 req/min per IP
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // OTP / email verification: 10 req/min per IP
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('upload', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Reports: 5 req/h per user (route-level throttle 防短期 spam；
        // service-level cache 對 system_issue 另有 24h 鎖)
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perHour(5)->by($request->user()?->id ?: $request->ip());
        });

        // PR-3: phone-change 5/min/user (fallback IP) — endpoint 必有 auth user
        RateLimiter::for('phone-change', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->user()?->id
                    ? 'user:' . $request->user()->id
                    : 'ip:' . $request->ip()
            );
        });

        $this->routes(function () {
            Route::middleware('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
