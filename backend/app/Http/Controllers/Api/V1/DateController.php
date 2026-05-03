<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DateInvitation;
use App\Services\DateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DateController extends Controller
{
    public function __construct(
        private readonly DateService $dateService,
    ) {}

    /**
     * GET /api/v1/dates — list invitations (flat array, for current user only).
     *
     * Cleanup PR-QR Step 2: 改為扁平陣列，明確 map 每個欄位（含 qr_token + expires_at）。
     *  - 不再回 Eloquent raw model（避免 leak 不該對外的欄位）
     *  - response 結構與 DateInvitationController::index 對齊，多 qr_token + expires_at
     *  - query 已用 inviter_id/invitee_id 過濾，僅回該 user 自己的邀請
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $invitations = DateInvitation::where(function ($q) use ($userId) {
                $q->where('inviter_id', $userId)
                  ->orWhere('invitee_id', $userId);
            })
            ->with(['inviter:id,nickname,avatar_url', 'invitee:id,nickname,avatar_url'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '約會列表查詢成功',
            'data' => [
                'invitations' => $invitations->map(fn ($inv) => [
                    'id' => $inv->id,
                    'inviter' => $inv->inviter ? [
                        'id' => $inv->inviter->id,
                        'nickname' => $inv->inviter->nickname,
                        'avatar' => $inv->inviter->avatar_url,
                    ] : null,
                    'invitee' => $inv->invitee ? [
                        'id' => $inv->invitee->id,
                        'nickname' => $inv->invitee->nickname,
                        'avatar' => $inv->invitee->avatar_url,
                    ] : null,
                    'scheduled_at' => $inv->date_time?->toISOString(),
                    'location' => $inv->location_name,
                    'status' => $inv->status,
                    'qr_token' => $inv->qr_token,
                    'expires_at' => $inv->expires_at?->toISOString(),
                    'created_at' => $inv->created_at,
                ])->values(),
            ],
        ]);
    }

    /**
     * POST /api/v1/dates — create invitation
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'invitee_id' => 'required|integer|exists:users,id',
            'date_time' => 'required|date|after:now',
            'location_name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $invitation = $this->dateService->createInvitation(
            $request->user(),
            $request->only(['invitee_id', 'date_time', 'location_name', 'latitude', 'longitude']),
        );

        return response()->json([
            'success' => true,
            'code' => 201,
            'message' => '約會邀請發送成功',
            'data' => [
                'invitation' => [
                    'id' => $invitation->id,
                    'qr_token' => $invitation->qr_token,
                    'status' => $invitation->status,
                    'date_time' => $invitation->date_time->toISOString(),
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'created_at' => $invitation->created_at,
                ],
            ],
        ], 201);
    }

    /**
     * PATCH /api/v1/dates/{id}/accept
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $inv = DateInvitation::findOrFail($id);

        try {
            $inv = $this->dateService->acceptInvitation($inv, $request->user());
        } catch (\Exception $e) {
            $code = $e->getMessage() === 'NOT_INVITEE' ? 403 : 422;
            return response()->json([
                'success' => false,
                'code' => $code,
                'message' => $e->getMessage(),
            ], $code);
        }

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '已接受約會邀請',
            'data' => ['invitation' => ['id' => $inv->id, 'status' => $inv->status]],
        ]);
    }

    /**
     * PATCH /api/v1/dates/{id}/decline
     */
    public function decline(Request $request, int $id): JsonResponse
    {
        $inv = DateInvitation::findOrFail($id);

        try {
            $inv = $this->dateService->declineInvitation($inv, $request->user());
        } catch (\Exception $e) {
            $code = $e->getMessage() === 'NOT_INVITEE' ? 403 : 422;
            return response()->json([
                'success' => false,
                'code' => $code,
                'message' => $e->getMessage(),
            ], $code);
        }

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '已拒絕約會邀請',
            'data' => ['invitation' => ['id' => $inv->id, 'status' => $inv->status]],
        ]);
    }

    /**
     * POST /api/v1/dates/verify — QR scan verification
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $result = $this->dateService->verifyQrToken(
                $request->input('token'),
                $request->user(),
                $request->input('latitude') ? (float) $request->input('latitude') : null,
                $request->input('longitude') ? (float) $request->input('longitude') : null,
            );
        } catch (\Exception $e) {
            $errorMap = [
                'TOKEN_NOT_FOUND' => 404,
                'TOKEN_EXPIRED' => 422,
                'TOKEN_ALREADY_USED' => 422,
                'TOKEN_CANCELLED' => 422,
                'SCAN_WINDOW_NOT_OPEN' => 422,
                'NOT_PARTICIPANT' => 403,
            ];
            $httpCode = $errorMap[$e->getMessage()] ?? 400;

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $e->getMessage(),
                    'message' => $e->getMessage(),
                ],
            ], $httpCode);
        }

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '驗證處理完成',
            'data' => $result,
        ]);
    }
}
