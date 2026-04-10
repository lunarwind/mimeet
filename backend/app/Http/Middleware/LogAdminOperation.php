<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AdminOperationLog;

class LogAdminOperation
{
    public function handle(Request $request, Closure $next, string $actionType = 'api_access'): mixed
    {
        $response = $next($request);

        try {
            $user = $request->user();
            AdminOperationLog::create([
                'admin_id' => $user?->id ?? 0,
                'admin_name' => $user?->nickname ?? $user?->email ?? 'unknown',
                'action_type' => $actionType,
                'resource_type' => $this->guessResourceType($request),
                'resource_id' => $request->route('id') ?? $request->route('userId') ?? null,
                'description' => $request->method() . ' ' . $request->path(),
                'metadata' => ['status' => $response->getStatusCode()],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning("[AdminLog] Failed to log: " . $e->getMessage());
        }

        return $response;
    }

    private function guessResourceType(Request $request): string
    {
        $path = $request->path();
        if (str_contains($path, 'members')) return 'member';
        if (str_contains($path, 'tickets')) return 'ticket';
        if (str_contains($path, 'payments')) return 'payment';
        if (str_contains($path, 'settings')) return 'settings';
        if (str_contains($path, 'chat-logs')) return 'chat_log';
        return 'other';
    }
}
