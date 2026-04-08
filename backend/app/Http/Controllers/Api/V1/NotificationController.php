<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => 0,
                'notifications' => [],
            ],
            'pagination' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 0],
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['marked_count' => 0],
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => true]);
    }
}
