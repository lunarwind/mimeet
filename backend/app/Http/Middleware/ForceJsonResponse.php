<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force API clients to be treated as JSON clients.
 *
 * Laravel's default validation layer redirects (302) when Accept is not
 * application/json. That's a footgun for curl / Postman / Swagger users
 * who forget the header. Forcing Accept:application/json on /api/* makes
 * validation always respond with 422 JSON instead.
 *
 * Exclusion: ECPay callback routes branch on $request->expectsJson() to
 * return either HTML (browser payment flow) or JSON (AJAX). Forcing JSON
 * there would break the HTML payment form.
 */
class ForceJsonResponse
{
    private const EXCLUDED_PATH_PREFIXES = [
        'api/v1/payments/ecpay',
        'api/v1/payments/return',         // ECPay OrderResultURL（POST browser redirect）
        'api/v1/verification/credit-card/return', // 信用卡驗證 browser redirect
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        foreach (self::EXCLUDED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
