<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DateInvitation;
use App\Services\DateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DateInvitationController extends Controller
{
    public function __construct(
        private readonly DateService $dateService,
    ) {}

    /**
     * POST /api/v1/date-invitations — create invitation (legacy endpoint)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'invitee_id' => 'required|integer|exists:users,id',
            'date_time' => 'required|date|after:now',
            'location_name' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
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
     * GET /api/v1/date-invitations — list invitations
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $invitations = DateInvitation::where('inviter_id', $userId)
            ->orWhere('invitee_id', $userId)
            ->with(['inviter:id,nickname,avatar_url', 'invitee:id,nickname,avatar_url'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '約會邀請列表查詢成功',
            'data' => ['invitations' => $invitations],
        ]);
    }

    /**
     * PATCH /api/v1/date-invitations/{id}/response — accept or decline
     */
    public function respond(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'data.response' => 'required|in:accepted,rejected',
        ]);

        $inv = DateInvitation::findOrFail($id);
        $response = $request->input('data.response', 'accepted');

        try {
            if ($response === 'accepted') {
                $inv = $this->dateService->acceptInvitation($inv, $request->user());
            } else {
                $inv = $this->dateService->declineInvitation($inv, $request->user());
            }
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
            'message' => '約會邀請回應成功',
            'data' => ['invitation' => ['id' => $inv->id, 'status' => $inv->status]],
        ]);
    }

    /**
     * POST /api/v1/date-invitations/verify — QR scan verification
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
                $request->input('token') ?? $request->input('data.qr_code'),
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
            'message' => '約會驗證成功',
            'data' => $result,
        ]);
    }
}
