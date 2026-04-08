<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ReportImage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}
    public function createReport(User $reporter, array $data, array $imageFiles = []): Report
    {
        $report = Report::create([
            'uuid' => Str::uuid()->toString(),
            'reporter_id' => $reporter->id,
            'reported_user_id' => $data['reported_user_id'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
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

        // Deduct points from both parties
        CreditScoreService::adjust($reporter, -10, 'report_filed', '送出檢舉');

        $reportedUser = User::find($data['reported_user_id']);
        if ($reportedUser) {
            CreditScoreService::adjust($reportedUser, -10, 'report_received', '被他人檢舉（待審）');
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
                CreditScoreService::adjust($reportedUser, -5, 'report_penalty', '檢舉屬實額外處分', $adminId);
                $reportedChange = -5;
            }
            $reporterScoreChange = 0;
        } elseif ($action === 'dismissed') {
            // Report dismissed — refund reporter
            if ($reporter) {
                CreditScoreService::adjust($reporter, +10, 'report_dismissed', '檢舉不成立退還分數', $adminId);
                $reporterScoreChange = +10;
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
