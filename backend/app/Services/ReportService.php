<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ReportImage;
use App\Models\User;
use App\Support\Mask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class ReportService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function createReport(
        User $reporter,
        array $data,
        array $imageFiles = [],
        ?Request $request = null,
    ): Report {
        $type = $data['type'] ?? '';
        $isSystemIssue = $type === 'system_issue';

        // v3.6: system_issue + sms_verification 子類別的 24h cache rate limit
        // category 標記為 sms_verification（給未來 admin filter 用）
        $description = (string) ($data['description'] ?? '');
        if ($isSystemIssue) {
            $cacheKey = "system_issue_throttle:{$reporter->id}:sms_verification";
            if (Cache::has($cacheKey)) {
                throw new TooManyRequestsHttpException(86400, '您 24 小時內已回報過，若仍有問題請聯繫客服');
            }

            // 自動補 metadata 並以 [META] JSON prefix 注入 description
            $meta = [
                'category' => 'sms_verification',
                'phone_masked' => Mask::phone($reporter->phone),
                'phone_hash' => $reporter->phone_hash,
                'phone_verified' => (bool) $reporter->phone_verified,
                'membership_level' => (int) $reporter->membership_level,
                'ip' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
                'reported_at' => now()->toISOString(),
            ];
            $description = '[META] ' . json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n[USER]\n" . $description;
        }

        $report = Report::create([
            'uuid' => Str::uuid()->toString(),
            'reporter_id' => $reporter->id,
            'reported_user_id' => $data['reported_user_id'] ?? null,
            'type' => $type,
            'description' => $description,
            'status' => 'pending',
        ]);

        // Store images
        foreach ($imageFiles as $file) {
            $path = Storage::disk('public')->put('reports', $file);
            ReportImage::create([
                'report_id' => $report->id,
                'image_url' => Storage::disk('public')->url($path),
            ]);
        }

        // Deduct points from both parties (skip for system issues)
        if (!$isSystemIssue) {
            CreditScoreService::adjust($reporter, -CreditScoreService::getConfig('credit_sub_report_user', 10), 'report_submit', '送出檢舉');

            $reportedUser = User::find($data['reported_user_id'] ?? null);
            if ($reportedUser) {
                CreditScoreService::adjust($reportedUser, -CreditScoreService::getConfig('credit_sub_report_user', 10), 'report_submit', '被他人檢舉（待審）');
            }
        }

        // v3.6: system_issue 成功建立後寫入 cache，24h 內擋下重複成功提交
        if ($isSystemIssue) {
            Cache::put("system_issue_throttle:{$reporter->id}:sms_verification", true, now()->addHours(24));
        }

        return $report;
    }

    public function resolveReport(Report $report, int $adminId, string $action, string $note): Report
    {
        // Prevent double-resolution
        if (in_array($report->status, ['resolved', 'dismissed'])) {
            return $report;
        }

        $reportedUser = User::find($report->reported_user_id);
        $reporter = User::find($report->reporter_id);
        $reporterScoreChange = 0;
        $reportedChange = 0;

        if ($action === 'resolved') {
            // Report confirmed — extra penalty for reported user
            if ($reportedUser) {
                $penalty = -CreditScoreService::getConfig('credit_sub_report_penalty', 5);
                CreditScoreService::adjust($reportedUser, $penalty, 'report_result_penalty', '檢舉屬實額外處分', $adminId);
                $reportedChange = $penalty;
            }
            $reporterScoreChange = 0;
        } elseif ($action === 'dismissed') {
            // Report dismissed — refund reporter
            if ($reporter) {
                $refund = CreditScoreService::getConfig('credit_add_report_refund', 10);
                CreditScoreService::adjust($reporter, $refund, 'report_result_refund', '檢舉不成立退還分數', $adminId);
                $reporterScoreChange = $refund;
            }
        }

        $report->update([
            'status' => $action,
            'resolved_by' => $adminId,
            'resolved_at' => now(),
            'resolution_note' => $note,
            'reporter_score_change' => $reporterScoreChange,
        ]);

        // Notify reporter
        if ($reporter) {
            $this->notificationService->notifyTicketReplied($reporter, $report->id);
        }

        Log::info('[ReportService] 結案通知 email 預留', [
            'report_id' => $report->id,
            'action' => $action,
        ]);

        return $report->fresh();
    }
}
