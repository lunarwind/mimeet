<?php

namespace App\Http\Middleware;

use App\Models\AdminOperationLog;
use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminOperation
{
    private const SENSITIVE_FIELDS = ['password', 'token', 'secret', 'api_key', 'current_password'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // PR-2 D14-a: 允許 controller 設 skip_admin_log attribute 跳過自動 log,
        // 改由 controller 自己寫結構化 log(避免兩筆重複 log)。
        if ($request->attributes->get('skip_admin_log') === true) {
            return $response;
        }

        // Only log mutating requests
        if (!in_array($request->method(), ['POST', 'PATCH', 'PUT', 'DELETE'])) {
            return $response;
        }

        $user = $request->user();
        if (!$user) {
            return $response;
        }

        // Determine action from route
        $routeName = $request->route()?->getName() ?? '';
        $action = $this->resolveAction($request);
        $resourceInfo = $this->resolveResource($request);

        // Sanitize request body
        $summary = $this->sanitizeBody($request->except(self::SENSITIVE_FIELDS));

        AdminOperationLog::create([
            'admin_id' => $user->id,
            'action' => $action,
            'resource_type' => $resourceInfo['type'],
            'resource_id' => $resourceInfo['id'],
            'description' => $request->method() . ' ' . $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'request_summary' => $summary,
            'created_at' => now(),
        ]);

        return $response;
    }

    private function resolveAction(Request $request): string
    {
        $path = $request->path();
        $method = $request->method();

        if (str_contains($path, 'members') && str_contains($path, 'actions')) {
            return $request->input('action', 'member_action');
        }
        if (str_contains($path, 'tickets')) return 'ticket_process';
        if (str_contains($path, 'verifications')) return 'verification_review';
        if (str_contains($path, 'broadcasts')) return 'broadcast_manage';
        if (str_contains($path, 'settings')) return 'settings_change';
        if (str_contains($path, 'app-mode')) return 'system_settings_change';
        if ($method === 'DELETE') return 'delete';

        return strtolower($method) . '_' . last(explode('/', $path));
    }

    private function resolveResource(Request $request): array
    {
        $params = $request->route()?->parameters() ?? [];
        $id = $params['id'] ?? $params['userId'] ?? null;

        $path = $request->path();
        if (str_contains($path, 'members')) return ['type' => 'member', 'id' => $id];
        if (str_contains($path, 'tickets')) return ['type' => 'ticket', 'id' => $id];
        if (str_contains($path, 'verifications')) return ['type' => 'verification', 'id' => $id];
        if (str_contains($path, 'broadcasts')) return ['type' => 'broadcast', 'id' => $id];

        return ['type' => 'system', 'id' => null];
    }

    private function sanitizeBody(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), self::SENSITIVE_FIELDS)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeBody($value);
            }
        }

        return $data;
    }
}
