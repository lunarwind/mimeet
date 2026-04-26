<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * StatsController 測試
 *
 * 核心驗證：GET /admin/stats/summary 的 members.total
 * 與 GET /admin/members 的 meta.total 口徑完全一致。
 */
class StatsControllerTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = AdminUser::factory()->create(['role' => 'super_admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    // ─── Case 1：N 位 active 用戶 → total === N ─────────────────────

    /** @test */
    public function summary_total_equals_active_user_count(): void
    {
        User::factory()->count(3)->create(['status' => 'active']);

        $res = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/admin/stats/summary');

        $res->assertOk();
        $this->assertSame(3, $res->json('data.members.total'));
    }

    // ─── Case 2：含軟刪除 → 軟刪除不計入 ────────────────────────────

    /** @test */
    public function summary_excludes_soft_deleted_users(): void
    {
        User::factory()->count(2)->create(['status' => 'active']);
        User::factory()->create(['status' => 'active', 'deleted_at' => now()]);

        $res = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/admin/stats/summary');

        $res->assertOk();
        $this->assertSame(2, $res->json('data.members.total'),
            '軟刪除用戶不應計入 members.total');
    }

    // ─── Case 3：paid 欄位也排除軟刪除 ───────────────────────────────

    /** @test */
    public function summary_paid_excludes_soft_deleted_paid_users(): void
    {
        // 2 active paid + 1 soft-deleted paid
        User::factory()->count(2)->create(['membership_level' => 3, 'status' => 'active']);
        User::factory()->create([
            'membership_level' => 3,
            'status'           => 'active',
            'deleted_at'       => now(),
        ]);

        $res = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/admin/stats/summary');

        $res->assertOk();
        $this->assertSame(2, $res->json('data.members.paid'),
            '軟刪除的付費用戶不應計入 members.paid');
    }

    // ─── Case 4（核心防線）：Dashboard total === List meta.total ────

    /** @test */
    public function summary_total_equals_members_list_meta_total(): void
    {
        // 建立 3 active + 1 soft-deleted
        User::factory()->count(3)->create(['status' => 'active']);
        User::factory()->create(['status' => 'active', 'deleted_at' => now()]);

        $summaryRes = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/admin/stats/summary');

        $listRes = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/admin/members?per_page=1');

        $summaryRes->assertOk();
        $listRes->assertOk();

        $summaryTotal = $summaryRes->json('data.members.total');
        $listTotal    = $listRes->json('meta.total');

        $this->assertSame($listTotal, $summaryTotal,
            "Dashboard total ({$summaryTotal}) 與 List meta.total ({$listTotal}) 必須一致。" .
            '如果 CI 在此失敗，代表某一端被加了不一致的過濾條件。'
        );
    }

    // ─── 輔助：summary 其他欄位格式完整性 ────────────────────────────

    /** @test */
    public function summary_returns_required_structure(): void
    {
        $res = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/v1/admin/stats/summary');

        $res->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'members' => ['total', 'new_today', 'new_month', 'paid', 'active'],
                    'revenue' => ['subscription_month', 'points_month', 'points_today'],
                    'points'  => ['circulating', 'consumed_today', 'consumed_month'],
                ],
            ]);
    }
}
