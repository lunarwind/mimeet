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
    public function submitAppeal(User $user, string $reason, array $imageFiles = []): Report
    {
        if (!in_array($user->status, ['auto_suspended', 'suspended'])) {
            throw new \Exception('NOT_SUSPENDED');
        }

        // Check duplicate appeal in same suspension period
        $existingAppeal = Report::where('type', 'appeal')
            ->where('reporter_id', $user->id)
            ->when($user->suspended_at, fn ($q) => $q->where('created_at', '>', $user->suspended_at))
            ->whereIn('status', ['pending', 'investigating'])
            ->first();

        if ($existingAppeal) {
            throw new \Exception('APPEAL_EXISTS');
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
