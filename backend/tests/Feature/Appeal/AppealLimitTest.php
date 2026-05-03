<?php

namespace Tests\Feature\Appeal;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cleanup PR-C：申訴頻率限制（同停權期間最多 3 次）
 */
class AppealLimitTest extends TestCase
{
    use RefreshDatabase;

    private function suspendedUser(?\Carbon\Carbon $suspendedAt = null): User
    {
        return User::factory()->create([
            'membership_level' => 1,
            'phone_verified'   => true,
            'status'           => 'auto_suspended',
            'credit_score'     => 0,
            'suspended_at'     => $suspendedAt ?? now()->subHour(),
        ]);
    }

    private function submitOnce(User $user, string $reason = '測試申訴'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->postJson('/api/v1/me/appeal', [
            'reason' => $reason,
        ]);
    }

    private function resolveActive(User $user): void
    {
        // 把 active appeal 改 status=resolved 來通過「同時 1 筆 active」的限制，
        // 模擬 admin 處理完，user 又要再次申訴
        Report::where('reporter_id', $user->id)
            ->where('type', 'appeal')
            ->whereIn('status', ['pending', 'investigating'])
            ->update(['status' => 'resolved']);
    }

    // ─── Case 13: 第 1-3 次申訴成功 ───
    public function test_appeals_1_to_3_succeed(): void
    {
        $user = $this->suspendedUser();

        for ($i = 1; $i <= 3; $i++) {
            $resp = $this->submitOnce($user, "申訴 #{$i}");
            $resp->assertStatus(201);
            $this->resolveActive($user);
        }

        $this->assertEquals(3, Report::where('reporter_id', $user->id)
            ->where('type', 'appeal')->count());
    }

    // ─── Case 14: 第 4 次申訴 → 422 APPEAL_LIMIT_REACHED ───
    public function test_fourth_appeal_returns_limit_reached(): void
    {
        $user = $this->suspendedUser();

        for ($i = 1; $i <= 3; $i++) {
            $this->submitOnce($user)->assertStatus(201);
            $this->resolveActive($user);
        }

        $resp = $this->submitOnce($user);
        $resp->assertStatus(422)
            ->assertJsonPath('error.code', 'APPEAL_LIMIT_REACHED');
    }

    // ─── Case 15: user 解停 → 重新被停權 → 計數歸零，可再申訴 ───
    public function test_appeal_count_resets_when_user_re_suspended(): void
    {
        $user = $this->suspendedUser(now()->subDays(10));

        // 在第 1 次停權期間用完 3 次
        for ($i = 1; $i <= 3; $i++) {
            $this->submitOnce($user)->assertStatus(201);
            $this->resolveActive($user);
        }
        $this->submitOnce($user)->assertStatus(422); // 達上限

        // 模擬解停
        $user->forceFill(['status' => 'active', 'suspended_at' => null])->save();

        // 重新被停權，新 suspended_at 一定要嚴格晚於先前 3 筆 appeal 的 created_at
        // （SQLite 時間精度可能與 now() 衝突，明確 +1 秒避開）
        $user->forceFill([
            'status'       => 'auto_suspended',
            'credit_score' => 0,
            'suspended_at' => now()->addSecond(),
        ])->save();

        // 新申訴成功（計數歸零）
        $this->submitOnce($user, '新停權期間第一次申訴')->assertStatus(201);
    }

    // ─── Case 16: 在停權期間「之前」的歷史申訴不計入 ───
    public function test_appeals_before_current_suspension_dont_count(): void
    {
        $user = $this->suspendedUser(now()->subHour());

        // 注入 3 筆「歷史申訴」於當次 suspended_at 之前
        // Report.created_at 不在 $fillable，用 DB::table 直接寫入避開 mass-assignment
        for ($i = 1; $i <= 3; $i++) {
            \DB::table('reports')->insert([
                'uuid'             => fake()->uuid(),
                'reporter_id'      => $user->id,
                'reported_user_id' => $user->id,
                'type'             => 'appeal',
                'description'      => "舊申訴 #{$i}",
                'status'           => 'resolved',
                'created_at'       => now()->subDays(30 + $i),
                'updated_at'       => now()->subDays(30 + $i),
            ]);
        }

        // 在當次停權期間還可以申訴 3 次
        for ($i = 1; $i <= 3; $i++) {
            $resp = $this->submitOnce($user, "本次申訴 #{$i}");
            $resp->assertStatus(201);
            $this->resolveActive($user);
        }

        // 第 4 次（本次停權期間）才會被擋
        $this->submitOnce($user)->assertStatus(422)
            ->assertJsonPath('error.code', 'APPEAL_LIMIT_REACHED');
    }
}
