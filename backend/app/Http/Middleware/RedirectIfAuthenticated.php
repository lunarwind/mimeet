<?php
namespace App\Http\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Closure;
class RedirectIfAuthenticated {
    public function handle(Request $request, Closure $next, string ...$guards) { return $next($request); }
}
