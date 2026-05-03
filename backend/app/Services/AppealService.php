<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ReportImage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppealService
{
    /**
     * 同停權期間最多 3 次申訴（含已處理）+ 同時最多 1 筆 active。
     * 「停權期間」起算 = user.suspended_at（最近一次被停權的時間）。
     */
    public const APPEAL_LIMIT_PER_SUSPENSION = 3;

    public function submitAppeal(User $user, string $reason, array $imageFiles = []): Report
    {
        if (!in_array($user->status, ['auto_suspended', 'suspended'])) {
            throw new \Exception('NOT_SUSPENDED');
        }

        // ── 1. 同時最多 1 筆 active appeal ──
        $existingAppeal = Report::where('type', 'appeal')
            ->where('reporter_id', $user->id)
            ->when($user->suspended_at, fn ($q) => $q->where('created_at', '>', $user->suspended_at))
            ->whereIn('status', ['pending', 'investigating'])
            ->first();

        if ($existingAppeal) {
            throw new \Exception('APPEAL_EXISTS');
        }

        // ── 2. 同停權期間最多 3 次申訴（不論已處理或審核中）── PR-C
        if ($user->suspended_at) {
            $appealCount = Report::where('type', 'appeal')
                ->where('reporter_id', $user->id)
                ->where('created_at', '>=', $user->suspended_at)
                ->count();

            if ($appealCount >= self::APPEAL_LIMIT_PER_SUSPENSION) {
                throw new \Exception('APPEAL_LIMIT_REACHED');
            }
        }

        $report = Report::create([
            'uuid' => Str::uuid()->toString(),
            'reporter_id' => $user->id,
            'reported_user_id' => $user->id,
            'type' => 'appeal',
            'description' => $reason,
            'status' => 'pending',
        ]);

        foreach ($imageFiles as $file) {
            $path = Storage::disk('public')->put("appeals/{$user->id}", $file);
            ReportImage::create([
                'report_id' => $report->id,
                'image_url' => Storage::disk('public')->url($path),
            ]);
        }

        Log::info("[Appeal] user #{$user->id} submitted appeal, report #{$report->id}");

        return $report;
    }

    public function getCurrentAppeal(User $user): ?Report
    {
        return Report::where('type', 'appeal')
            ->where('reporter_id', $user->id)
            ->orderByDesc('created_at')
            ->first();
    }
}
