<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditCardVerification;
use App\Services\CreditCardVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditCardVerificationController extends Controller
{
    public function __construct(private CreditCardVerificationService $service) {}

    /**
     * GET /api/v1/admin/credit-card-verifications
     * List all credit card verifications with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CreditCardVerification::with('user:id,nickname,email,gender')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $items = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $items->map(fn ($v) => [
                'id' => $v->id,
                'user' => $v->user ? [
                    'id' => $v->user->id,
                    'nickname' => $v->user->nickname,
                    'email' => $v->user->email,
                ] : null,
                'order_no' => $v->order_no,
                'amount' => $v->amount,
                'status' => $v->status,
                'gateway_trade_no' => $v->gateway_trade_no,
                'card_last4' => $v->card_last4,
                'paid_at' => $v->paid_at?->toISOString(),
                'refunded_at' => $v->refunded_at?->toISOString(),
                'created_at' => $v->created_at?->toISOString(),
            ]),
            'meta' => [
                'page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/credit-card-verifications/{id}/refund
     * Manually trigger a refund for a specific verification.
     */
    public function refund(Request $request, int $id): JsonResponse
    {
        $verification = CreditCardVerification::findOrFail($id);
        $ok = $this->service->refund($verification);

        if ($ok) {
            return response()->json(['success' => true, 'message' => '退款成功']);
        }
        return response()->json(['success' => false, 'message' => '退款失敗，請查看系統日誌'], 422);
    }
}
