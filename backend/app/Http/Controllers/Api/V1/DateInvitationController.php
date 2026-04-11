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
     * POST /api/v1/date-invitations — create invitation (legacy route)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'data.invitee_id' => 'required|integer|exists:users,id',
            'data.scheduled_at' => 'required|date|after:now',
            'data.location' => 'nullable|string|max:255',
            'data.location_lat' => 'required|numeric|between:-90,90',
            'data.location_lng' => 'required|numeric|between:-180,180',
            'data.message' => 'nullable|string|max:500',
        ]);

        $input = $request->input('data');

        $invitation = $this->dateService->createInvitation(
            $request->user(),
            [
                'invitee_id' => $input['invitee_id'],
                'date_time' => $input['scheduled_at'],
                'location_name' => $input['location'] ?? null,
                'latitude' => $input['location_lat'],
                'longitude' => $input['location_lng'],
            ],
        );

        $invitation->load(['inviter:id,nickname,avatar_url', 'invitee:id,nickname,avatar_url']);

        return response()->json([
            'success' => true,
            'code' => 201,
            'message' => '約會邀請發送成功',
            'data' => [
                'invitation' => [
                    'id' => $invitation->id,
                    'inviter_id' => $invitation->inviter_id,
                    'invitee_id' => $invitation->invitee_id,
                    'scheduled_at' => $invitation->date_time->toISOString(),
                    'location' => $invitation->location_name,
                    'location_lat' => $invitation->latitude,
                    'location_lng' => $invitation->longitude,
                    'status' => $invitation->status,
                    'qr_code' => $invitation->qr_token,
                    'qr_expires_at' => $invitation->expires_at->toISOString(),
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
        $status = $request->query('status');
        $type = $request->query('type'); // sent | received

        $query = DateInvitation::query()
            ->with(['inviter:id,nickname,avatar_url', 'invitee:id,nickname,avatar_url']);

        if ($type === 'sent') {
            $query->where('inviter_id', $userId);
        } elseif ($type === 'received') {
            $query->where('invitee_id', $userId);
        } else {
            $query->where(fn ($q) => $q->where('inviter_id', $userId)->orWhere('invitee_id', $userId));
        }

        if ($status) {
            $query->where('status', $status);
        }

        $invitations = $query->orderByDesc('created_at')
            ->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '約會邀請列表查詢成功',
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
                    'created_at' => $inv->created_at,
                ]),
            ],
            'pagination' => [
                'current_page' => $invitations->currentPage(),
                'per_page' => $invitations->perPage(),
                'total' => $invitations->total(),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/date-invitations/{id}/response — accept or reject
     */
    public function respond(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'data.response' => 'required|string|in:accepted,rejected',
        ]);

        $inv = DateInvitation::findOrFail($id);
        $response = $request->input('data.response');

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
            'data' => [
                'invitation' => [
                    'id' => $inv->id,
                    'status' => $inv->status,
                    'responded_at' => now()->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/date-invitations/verify — QR scan verification (legacy route)
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
                'NOT_PARTICIPANT' => 403,
            ];
            $httpCode = $errorMap[$e->getMessage()] ?? 400;

            return response()->json([
                'success' => false,
                'code' => $httpCode,
                'message' => $e->getMessage(),
                'error' => [
                    'type' => 'verification_failed',
                    'reason' => $e->getMessage(),
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
