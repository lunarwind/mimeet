<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CreditScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CreditScoreSubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure credit score settings exist with spec defaults
        $specDefaults = [
            'credit_sub_report_user'         => '10',
            'credit_sub_report_penalty'      => '5',
            'credit_add_report_refund'       => '10',
            'credit_score_unblock_threshold' => '30',
            'credit_admin_reward_max'        => '20',
            'credit_admin_penalty_max'       => '20',
        ];
        foreach ($specDefaults as $key => $value) {
            SystemSetting::updateOrCreate(['key_name' => $key], ['value' => $value, 'value_type' => 'integer']);
            Cache::forget("setting:{$key}");
            Cache::forget("sys:{$key}");
        }
    }

    /** @test */
    public function getConfig_reads_positive_value_from_settings(): void
    {
        SystemSetting::updateOrCreate(['key_name' => 'credit_sub_report_user'], ['value' => '10']);
        Cache::forget('setting:credit_sub_report_user');
        Cache::forget('sys:credit_sub_report_user');

        $value = CreditScoreService::getConfig('credit_sub_report_user', 10);
        $this->assertEquals(10, $value, 'getConfig 應讀取正值 10');
    }

    /** @test */
    public function report_submit_deducts_score_from_both_parties(): void
    {
        $reporter = User::factory()->create(['credit_score' => 60, 'gender' => 'male', 'membership_level' => 1]);
        $reported = User::factory()->create(['credit_score' => 60, 'gender' => 'female', 'membership_level' => 1]);

        $reporter->forceFill(['phone_verified' => true, 'email_verified' => true])->save();
        $reported->forceFill(['phone_verified' => true, 'email_verified' => true])->save();

        $this->actingAs($reporter, 'sanctum')
            ->postJson('/api/v1/reports', [
                'reported_user_id' => $reported->id,
                'type' => 'harassment',
                'description' => '測試檢舉',
            ])
            ->assertCreated();

        $this->assertEquals(50, $reporter->fresh()->credit_score, '檢舉人應扣 10 分');
        $this->assertEquals(50, $reported->fresh()->credit_score, '被檢舉人應扣 10 分');
    }

    /** @test */
    public function report_dismissed_refunds_reporter(): void
    {
        [$reporter, $reported, $admin] = $this->createUsersAndAdmin();

        // Submit report → both lose 10
        $report = \App\Models\Report::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reported->id,
            'type' => 'harassment',
            'status' => 'pending',
        ]);
        CreditScoreService::adjust($reporter, -10, 'report_submit', '送出檢舉');

        $scoreBefore = $reporter->fresh()->credit_score;

        // Admin dismisses → reporter refunded
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/tickets/{$report->id}", [
                'status' => 'dismissed',
                'admin_reply' => '查無實據',
            ])
            ->assertOk();

        $this->assertEquals($scoreBefore + 10, $reporter->fresh()->credit_score, '退款後應加回 10 分');
    }

    /** @test */
    public function report_resolved_adds_extra_penalty_to_reported(): void
    {
        [$reporter, $reported, $admin] = $this->createUsersAndAdmin();

        $report = \App\Models\Report::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reported->id,
            'type' => 'harassment',
            'status' => 'pending',
        ]);

        $reportedScoreBefore = $reported->fresh()->credit_score;

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/tickets/{$report->id}", [
                'status' => 'resolved',
                'admin_reply' => '屬實',
            ])
            ->assertOk();

        $this->assertEquals(
            $reportedScoreBefore - 5,
            $reported->fresh()->credit_score,
            '檢舉屬實應額外扣 5 分（credit_sub_report_penalty）'
        );
    }

    /** @test */
    public function unblock_threshold_uses_system_setting(): void
    {
        $user = User::factory()->create(['credit_score' => 25, 'status' => 'auto_suspended']);

        // Default threshold is 30 — at 25, should not restore
        CreditScoreService::adjust($user, 4, 'admin_reward', '測試');
        $this->assertEquals('auto_suspended', $user->fresh()->status, '29 分不應觸發解停');

        // Change threshold to 28
        SystemSetting::updateOrCreate(['key_name' => 'credit_score_unblock_threshold'], ['value' => '28']);
        Cache::forget('sys:credit_score_unblock_threshold');

        // Now +1 to reach 30 which is > 28
        CreditScoreService::adjust($user, 1, 'admin_reward', '測試');
        $this->assertEquals('active', $user->fresh()->status, '30 分（> 門檻28）應觸發解停');
    }

    /** @test */
    public function admin_adjust_score_respects_system_setting_max(): void
    {
        $admin = \App\Models\AdminUser::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['credit_score' => 60]);

        $token = $admin->createToken('test')->plainTextToken;

        // +20 OK (default reward_max)
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson("/api/v1/admin/members/{$target->id}/actions", [
                'action' => 'adjust_score',
                'score_delta' => 20,
                'reason' => '測試',
            ])
            ->assertOk();

        // +21 should fail
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson("/api/v1/admin/members/{$target->id}/actions", [
                'action' => 'adjust_score',
                'score_delta' => 21,
                'reason' => '測試',
            ])
            ->assertStatus(422);

        // -20 OK
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson("/api/v1/admin/members/{$target->id}/actions", [
                'action' => 'adjust_score',
                'score_delta' => -20,
                'reason' => '測試',
            ])
            ->assertOk();

        // -21 should fail
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson("/api/v1/admin/members/{$target->id}/actions", [
                'action' => 'adjust_score',
                'score_delta' => -21,
                'reason' => '測試',
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function admin_adjust_limit_changes_when_setting_updated(): void
    {
        SystemSetting::updateOrCreate(['key_name' => 'credit_admin_reward_max'], ['value' => '30']);
        Cache::forget('sys:credit_admin_reward_max');

        $admin = \App\Models\AdminUser::factory()->create(['role' => 'super_admin']);
        $target = User::factory()->create(['credit_score' => 60]);

        $token = $admin->createToken('test')->plainTextToken;

        // +30 now OK
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson("/api/v1/admin/members/{$target->id}/actions", [
                'action' => 'adjust_score',
                'score_delta' => 30,
                'reason' => '測試',
            ])
            ->assertOk();
    }

    /** @test */
    public function score_delta_zero_is_rejected(): void
    {
        $admin = \App\Models\AdminUser::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['credit_score' => 60]);

        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->patchJson("/api/v1/admin/members/{$target->id}/actions", [
                'action' => 'adjust_score',
                'score_delta' => 0,
                'reason' => '測試',
            ])
            ->assertStatus(422);
    }

    // ─── Helper ──────────────────────────────────────────────────────

    private function createUsersAndAdmin(): array
    {
        $reporter = User::factory()->create([
            'credit_score' => 60, 'gender' => 'male', 'membership_level' => 1,
            'phone_verified' => true, 'email_verified' => true,
        ]);
        $reported = User::factory()->create([
            'credit_score' => 60, 'gender' => 'female', 'membership_level' => 1,
            'phone_verified' => true, 'email_verified' => true,
        ]);
        $admin = \App\Models\AdminUser::factory()->create(['role' => 'super_admin']);
        return [$reporter, $reported, $admin];
    }
}
