<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\IssueInvoiceJob;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理後台：手動補開電子發票（super_admin only）
 *
 * POST /api/v1/admin/payments/{id}/issue-invoice
 */
class PaymentInvoiceController extends Controller
{
    public function issueInvoice(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin || $admin->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PERMISSION_DENIED', 'message' => '僅超級管理員可手動補開發票'],
            ], 403);
        }

        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PAYMENT_NOT_FOUND', 'message' => '付款記錄不存在'],
            ], 404);
        }

        if ($payment->status !== 'paid') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PAYMENT_NOT_PAID', 'message' => '此付款尚未完成，無法開立發票'],
            ], 422);
        }

        if ($payment->invoice_no) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVOICE_ALREADY_ISSUED', 'message' => '發票已開立：' . $payment->invoice_no],
            ], 422);
        }

        $payment->update(['invoice_status' => 'pending']);
        IssueInvoiceJob::dispatch($payment->id);

        return response()->json([
            'success' => true,
            'message' => '已排入發票開立佇列，預計 1 分鐘內完成',
            'data'    => ['order_no' => $payment->order_no, 'type' => $payment->type],
        ], 202);
    }
}
