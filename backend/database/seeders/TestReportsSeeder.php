<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestReportsSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('id', '>', 1)->where('status', 'active')->get();
        if ($users->count() < 4) return;

        $reports = [
            ['type' => 'harassment', 'status' => 'pending', 'desc' => '對方傳送騷擾訊息，多次要求見面被拒後仍持續騷擾'],
            ['type' => 'scam', 'status' => 'investigating', 'desc' => '疑似詐騙帳號，要求提供銀行帳號'],
            ['type' => 'fake_photo', 'status' => 'resolved', 'desc' => '使用網路上的明星照片作為頭像'],
            ['type' => 'other', 'status' => 'pending', 'desc' => '系統問題：無法上傳照片，一直顯示失敗'],
            ['type' => 'harassment', 'status' => 'resolved', 'desc' => '連續傳送大量訊息，已截圖為證'],
            ['type' => 'inappropriate', 'status' => 'dismissed', 'desc' => '個人資料包含不適當內容'],
        ];

        foreach ($reports as $i => $r) {
            $reporter = $users->random();
            $reported = $users->where('id', '!=', $reporter->id)->random();

            Report::create([
                'uuid' => Str::uuid()->toString(),
                'reporter_id' => $reporter->id,
                'reported_user_id' => $reported->id,
                'type' => $r['type'],
                'description' => $r['desc'],
                'status' => $r['status'],
                'resolved_by' => in_array($r['status'], ['resolved', 'dismissed']) ? 1 : null,
                'resolved_at' => in_array($r['status'], ['resolved', 'dismissed']) ? now()->subDays(rand(1, 5)) : null,
                'resolution_note' => $r['status'] === 'resolved' ? '已查證屬實，已對違規用戶進行處理' : ($r['status'] === 'dismissed' ? '檢舉不成立' : null),
                'created_at' => now()->subDays(rand(3, 20)),
            ]);
        }

        $this->command->info('Created ' . Report::count() . ' reports');
    }
}
