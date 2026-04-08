<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireMembership
{
    /**
     * Handle an incoming request.
     *
     * Check that the authenticated user's membership_level is >= the required level.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int  $level  Required membership level
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, int $level = 1): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => '請先登入。',
            ], 401);
        }

        if ((int) $user->membership_level < $level) {
            return response()->json([
                'success' => false,
                'code' => 'MEMBERSHIP_REQUIRED',
                'message' => '此功能需要升級會員方案。',
                'data' => [
                    'required_level' => $level,
                    'current_level' => (int) $user->membership_level,
                ],
            ], 403);
        }

        return $next($request);
    }
}
