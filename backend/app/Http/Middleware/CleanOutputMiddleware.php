<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST body pollution guard.
 *
 * Detects and strips any content that was accidentally echoed before the
 * JSON response body. The real fix is output_buffering=4096 in the FPM
 * pool's www.conf (php_admin_value). This middleware is a second-line
 * defence that also logs the pollution so the root cause can be tracked.
 */
class CleanOutputMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ob_get_level() > 0) {
            ob_clean();
        }

        $response = $next($request);

        $content = $response->getContent();
        if (!$content) {
            return $response;
        }

        $first = ltrim($content)[0] ?? '';
        if ($first !== '{' && $first !== '[') {
            $jsonStart = false;
            foreach (['{', '['] as $c) {
                $pos = strpos($content, $c);
                if ($pos !== false && ($jsonStart === false || $pos < $jsonStart)) {
                    $jsonStart = $pos;
                }
            }

            if ($jsonStart !== false && $jsonStart > 0) {
                Log::warning('[CleanOutput] Response pollution stripped', [
                    'prefix_length' => $jsonStart,
                    'prefix'        => substr($content, 0, min($jsonStart, 300)),
                    'path'          => $request->path(),
                    'method'        => $request->method(),
                ]);
                $response->setContent(substr($content, $jsonStart));
            }
        }

        return $response;
    }
}
