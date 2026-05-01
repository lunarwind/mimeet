<?php

namespace Tests\Feature\Auth;

use App\Models\AdminUser;
use App\Models\User;
use App\Services\CreditScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Hot-fix C-minimal: 停權路徑必須撤銷既有 token，
 * 避免被停權使用者持有的 PAT 在 24h 內仍可使用。
 *
 * 對應決策：docs/decisions/2026-05-01-check-suspended-decision.md d.1
 * 對應 issue：audit-A-20260501-codex.md #A5-001
 */
class SuspendTokenRevokeTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveAdmin(): AdminUser
    {
        return AdminUser::create([
            'name'      => 'Suspend Test Admin',
            'email'     => 'suspend-admin@test.mimeet',
            'password'  => Hash::make('TestPass@2026'),
            'role'      => 'super_admin',
            'is_active' => true,
        ]);
    }

    private function adminBearer(AdminUser $admin): array
    {
        $token = $admin->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}"];
    }

    private function userTokenCount(User $user): int
    {
        return PersonalAccessToken::where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->count();
    }

    /** @test */
    public function admin_suspend_action_revokes_all_user_tokens(): void
    {
        $admin = $this->createActiveAdmin();
        $user  = User::factory()->create(['status' => 'active', 'credit_score' => 60]);

        // Simulate user signed in on 2 devices
        $userToken1 = $user->createToken('mobile')->plainTextToken;
        $user->createToken('desktop')->plainTextToken;
        $this->assertSame(2, $this->userTokenCount($user), 'precondition: user has 2 tokens');

        // Admin triggers suspend via /admin/members/{id}/actions
        $this->withHeaders($this->adminBearer($admin))
            ->patchJson("/api/v1/admin/members/{$user->id}/actions", [
                'action' => 'suspend',
            ])
            ->assertOk();

        // All user tokens should be gone
        $this->assertSame(0, $this->userTokenCount($user),
            'admin suspend action must revoke all user tokens');

        // Old token must now be rejected on a protected endpoint
        $this->withHeaders(['Authorization' => "Bearer {$userToken1}"])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        // Activity log row should be present
        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $user->id,
            'action'  => 'account_suspended_by_admin',
        ]);
    }

    /** @test */
    public function admin_patch_member_status_suspended_revokes_tokens(): void
    {
        $admin = $this->createActiveAdmin();
        $user  = User::factory()->create(['status' => 'active', 'credit_score' => 60]);

        $userToken = $user->createToken('mobile')->plainTextToken;
        $this->assertSame(1, $this->userTokenCount($user));

        // Admin updates status via /admin/members/{id}/permissions
        $this->withHeaders($this->adminBearer($admin))
            ->patchJson("/api/v1/admin/members/{$user->id}/permissions", [
                'status' => 'suspended',
            ])
            ->assertOk();

        $this->assertSame(0, $this->userTokenCount($user),
            'PATCH /admin/members/{id}/permissions with status=suspended must revoke tokens');

        $this->withHeaders(['Authorization' => "Bearer {$userToken}"])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $user->id,
            'action'  => 'account_suspended_by_admin',
        ]);
    }

    /** @test */
    public function credit_score_zero_auto_suspend_revokes_tokens(): void
    {
        // Start with low score so we can drive it to 0 with a small adjust
        $user = User::factory()->create([
            'status'       => 'active',
            'credit_score' => 5,
        ]);

        $userToken = $user->createToken('mobile')->plainTextToken;
        $this->assertSame(1, $this->userTokenCount($user));

        // Drop credit_score to 0 → CreditScoreObserver::updated fires auto_suspend
        CreditScoreService::adjust($user, -5, 'admin_penalty', 'test auto-suspend at zero', null);

        $user->refresh();
        $this->assertSame('auto_suspended', $user->status,
            'CreditScoreObserver should auto-suspend when score reaches 0');

        $this->assertSame(0, $this->userTokenCount($user),
            'auto-suspend (score reaches 0) must revoke tokens');

        $this->withHeaders(['Authorization' => "Bearer {$userToken}"])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    /** @test */
    public function admin_unsuspend_does_not_revoke_unrelated_admin_tokens(): void
    {
        // Defensive: ensure $user->tokens()->delete() does NOT touch the admin's tokens
        $admin = $this->createActiveAdmin();
        $adminBearer = $this->adminBearer($admin);

        $user = User::factory()->create(['status' => 'active', 'credit_score' => 60]);
        $user->createToken('mobile')->plainTextToken;

        $this->withHeaders($adminBearer)
            ->patchJson("/api/v1/admin/members/{$user->id}/actions", [
                'action' => 'suspend',
            ])
            ->assertOk();

        // Admin should still be authenticated for follow-up calls
        $this->withHeaders($adminBearer)
            ->getJson('/api/v1/admin/auth/me')
            ->assertOk();
    }
}
