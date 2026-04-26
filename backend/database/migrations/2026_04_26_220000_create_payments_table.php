<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 建立統一 payments 主表，整合三個金流入口：
 *   - verification (CCV_)  信用卡身份驗證
 *   - subscription (MM)    會員購買
 *   - points       (PTS_)  點數儲值
 *
 * 現有 7 筆測試資料（orders 3 + point_orders 3 + credit_card_verifications 1）
 * 全部轉入並標記 environment='legacy'、status='cancelled'。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. 建立 payments 主表 ──────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['verification', 'subscription', 'points'])->index();
            $table->string('order_no', 50)->unique()->comment('CCV_ / MM / PTS_ 前綴');
            $table->string('item_name', 200)->nullable();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('TWD');
            $table->enum('status', [
                'pending', 'paid', 'failed', 'cancelled',
                'refunded', 'refund_failed',
            ])->default('pending')->index();
            $table->string('gateway', 20)->default('ecpay');
            $table->enum('environment', ['sandbox', 'production', 'legacy'])
                  ->default('sandbox')->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index()
                  ->comment('業務表 ID：orders.id / point_orders.id / credit_card_verifications.id');
            // ECPay 回傳欄位
            $table->string('gateway_trade_no', 50)->nullable()->index();
            $table->string('card_country', 4)->nullable();
            $table->string('payment_method', 30)->nullable()->comment('e.g. Credit_CreditCard');
            // 時間戳
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refund_scheduled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_trade_no', 50)->nullable();
            $table->text('refund_failure_reason')->nullable();
            // 發票（保留欄位，功能待啟用）
            $table->string('invoice_no', 30)->nullable();
            $table->timestamp('invoice_issued_at')->nullable();
            // 錯誤與原始資料
            $table->text('failure_reason')->nullable();
            $table->json('raw_callback')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['status', 'refund_scheduled_at']);
            $table->index(['user_id', 'type', 'status']);
        });

        // ── 2. 舊業務表加 payment_id FK ──────────────────────────
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable()->after('id')->index()
                  ->comment('[DEPRECATED] 關聯 payments.id，將由 payments.reference_id 反向查詢');
        });

        Schema::table('point_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable()->after('id')->index()
                  ->comment('[DEPRECATED] 關聯 payments.id');
        });

        Schema::table('credit_card_verifications', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable()->after('user_id')->index()
                  ->comment('[DEPRECATED] 關聯 payments.id');
        });

        // ── 3. 遷移既有 7 筆測試資料 → payments（標記 legacy）────
        // 3a. orders 3 筆（訂閱 mock，全部 pending）
        $orders = DB::table('orders')->get();
        foreach ($orders as $o) {
            $paymentId = DB::table('payments')->insertGetId([
                'user_id'         => $o->user_id,
                'type'            => 'subscription',
                'order_no'        => $o->order_number,
                'item_name'       => null,
                'amount'          => $o->amount,
                'currency'        => $o->currency ?? 'TWD',
                'status'          => 'cancelled',
                'gateway'         => 'ecpay',
                'environment'     => 'legacy',
                'reference_id'    => $o->id,
                'gateway_trade_no'=> $o->ecpay_trade_no,
                'payment_method'  => $o->payment_method,
                'paid_at'         => $o->paid_at,
                'failure_reason'  => 'legacy_mock_before_unified_gateway',
                'created_at'      => $o->created_at,
                'updated_at'      => now(),
            ]);
            DB::table('orders')->where('id', $o->id)->update(['payment_id' => $paymentId]);
        }

        // 3b. point_orders 3 筆（1 pending + 2 paid mock）
        $pointOrders = DB::table('point_orders')->get();
        foreach ($pointOrders as $po) {
            $paymentId = DB::table('payments')->insertGetId([
                'user_id'         => $po->user_id,
                'type'            => 'points',
                'order_no'        => $po->trade_no,
                'item_name'       => null,
                'amount'          => $po->amount,
                'currency'        => 'TWD',
                'status'          => 'cancelled',
                'gateway'         => 'ecpay',
                'environment'     => 'legacy',
                'reference_id'    => $po->id,
                'gateway_trade_no'=> $po->gateway_trade_no,
                'payment_method'  => $po->payment_method ?? 'credit_card',
                'paid_at'         => $po->paid_at,
                'failure_reason'  => 'legacy_mock_before_unified_gateway',
                'created_at'      => $po->created_at,
                'updated_at'      => now(),
            ]);
            DB::table('point_orders')->where('id', $po->id)->update(['payment_id' => $paymentId]);
        }

        // 3c. credit_card_verifications 1 筆（pending）
        $verifications = DB::table('credit_card_verifications')->get();
        foreach ($verifications as $v) {
            $paymentId = DB::table('payments')->insertGetId([
                'user_id'         => $v->user_id,
                'type'            => 'verification',
                'order_no'        => $v->order_no,
                'item_name'       => 'MiMeet 信用卡身份驗證（NT$100）',
                'amount'          => $v->amount,
                'currency'        => 'TWD',
                'status'          => 'cancelled',
                'gateway'         => 'ecpay',
                'environment'     => 'legacy',
                'reference_id'    => $v->id,
                'gateway_trade_no'=> $v->gateway_trade_no,
                'payment_method'  => $v->payment_method,
                'paid_at'         => $v->paid_at,
                'failure_reason'  => 'legacy_mock_before_unified_gateway',
                'created_at'      => $v->created_at,
                'updated_at'      => now(),
            ]);
            DB::table('credit_card_verifications')
                ->where('id', $v->id)
                ->update(['payment_id' => $paymentId]);
        }
    }

    public function down(): void
    {
        Schema::table('credit_card_verifications', function (Blueprint $table) {
            $table->dropColumn('payment_id');
        });
        Schema::table('point_orders', function (Blueprint $table) {
            $table->dropColumn('payment_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_id');
        });
        Schema::dropIfExists('payments');
    }
};
