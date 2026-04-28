<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\EcpayInvoiceWordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 後台電子發票字軌管理
 * 全部限 super_admin
 */
class EcpayInvoiceWordController extends Controller
{
    public function __construct(private EcpayInvoiceWordService $service) {}

    /**
     * GET /admin/settings/ecpay/invoice-words?year=115&term=3
     */
    public function index(Request $request): JsonResponse
    {
        $this->guardSuperAdmin($request);

        $rocYear     = now()->year - 1911;
        $currentTerm = (int) ceil(now()->month / 2);
        $year  = (int) $request->input('year', $rocYear);
        $term  = (int) $request->input('term', $currentTerm);

        $result = $this->service->query($year, $term);
        if (!$result['ok']) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'QUERY_FAILED', 'message' => $result['msg'],
            ]], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data'] ?? [],
        ]);
    }

    /**
     * POST /admin/settings/ecpay/invoice-words
     */
    public function store(Request $request): JsonResponse
    {
        $this->guardSuperAdmin($request);

        $request->validate([
            'invoice_year' => 'required|string',
            'invoice_term' => 'required|integer|min:1|max:6',
            'header'       => 'required|string|size:2',
            'start'        => 'required|integer|min:0',
            'end'          => 'required|integer|min:0',
            'inv_type'     => 'sometimes|string|in:07,08',
        ]);

        $result = $this->service->add(
            (int) $request->invoice_term,
            $request->invoice_year,
            $request->header,
            (int) $request->start,
            (int) $request->end,
            $request->input('inv_type', '07'),
        );

        if (!$result['ok']) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'ADD_WORD_FAILED', 'message' => $result['msg'],
            ]], 422);
        }

        // 新增後立即啟用
        $autoEnabled = false;
        if ($trackId = ($result['track_id'] ?? null)) {
            $statusResult = $this->service->setStatus($trackId, true);
            $autoEnabled  = $statusResult['ok'];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'track_id'     => $result['track_id'] ?? null,
                'auto_enabled' => $autoEnabled,
                'message'      => $autoEnabled
                    ? '字軌新增並啟用成功'
                    : '字軌新增成功但啟用失敗，請手動啟用',
            ],
        ], 201);
    }

    /**
     * PATCH /admin/settings/ecpay/invoice-words/{trackId}/status
     * Body: { "enabled": true }
     */
    public function updateStatus(Request $request, string $trackId): JsonResponse
    {
        $this->guardSuperAdmin($request);
        $request->validate(['enabled' => 'required|boolean']);

        $result = $this->service->setStatus($trackId, (bool) $request->enabled);
        if (!$result['ok']) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'STATUS_UPDATE_FAILED', 'message' => $result['msg'],
            ]], 500);
        }

        return response()->json(['success' => true]);
    }

    private function guardSuperAdmin(Request $request): void
    {
        if (($request->user()->role ?? null) !== 'super_admin') {
            abort(403, '僅 super_admin 可管理字軌');
        }
    }
}
