<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F22 Part B — Global Do Not Disturb schedule
 * GET /api/v1/me/dnd   — 取得設定
 * PATCH /api/v1/me/dnd — 更新設定
 */
class DndController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $u = $request->user();
        return response()->json([
            'success' => true,
            'data' => [
                'dnd_enabled' => (bool) $u->dnd_enabled,
                'dnd_start' => $this->formatTime($u->dnd_start),
                'dnd_end' => $this->formatTime($u->dnd_end),
                'currently_active' => $u->isInDndPeriod(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dnd_enabled' => 'required|boolean',
            'dnd_start' => 'required_if:dnd_enabled,true|nullable|date_format:H:i',
            'dnd_end' => 'required_if:dnd_enabled,true|nullable|date_format:H:i',
        ]);

        $u = $request->user();
        $u->forceFill([
            'dnd_enabled' => $validated['dnd_enabled'],
            'dnd_start' => $validated['dnd_enabled'] ? $validated['dnd_start'] : null,
            'dnd_end' => $validated['dnd_enabled'] ? $validated['dnd_end'] : null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => '免打擾設定已更新',
            'data' => [
                'dnd_enabled' => (bool) $u->dnd_enabled,
                'dnd_start' => $this->formatTime($u->dnd_start),
                'dnd_end' => $this->formatTime($u->dnd_end),
                'currently_active' => $u->isInDndPeriod(),
            ],
        ]);
    }

    private function formatTime($value): ?string
    {
        if (!$value) return null;
        return substr((string) $value, 0, 5); // DB stored "HH:MM:SS" → "HH:MM"
    }
}
