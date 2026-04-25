<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests for the admin authentication flow and protected API access.
 * Ensures EnsureAdminUser middleware + AdminController work end-to-end.
 */
class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveAdmin(string $role = 'super_admin'): AdminUser
    {
        return AdminUser::create([
            'name'      => 'Test Admin',
            'email'     => 'admin@test.mimeet',
            'password'  => Hash::make('TestPass@2026'),
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    private function loginAndGetToken(AdminUser $admin): string
    {
        $res = $this->postJson('/api/v1/admin/auth/login', [
            'email'    => $admin->email,
            'password' => 'TestPass@2026',
        ]);

        $res->assertOk()->assertJsonPath('success', true);

        return $res->json('data.token');
    }

    // ── Login ───────────────────────────────────────────────────────

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = $this->createActiveAdmin();

        $this->postJson('/api/v1/admin/auth/login', [
            'email'    => $admin->email,
            'password' => 'TestPass@2026',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'admin' => ['id', 'email', 'role']]]);
    }

    public function test_admin_login_fails_with_wrong_password(): void
    {
        $admin = $this->createActiveAdmin();

        $this->postJson('/api/v1/admin/auth/login', [
            'email'    => $admin->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_inactive_admin_cannot_login(): void
    {
        $admin = $this->createActiveAdmin();
        $admin->update(['is_active' => false]);

        $this->postJson('/api/v1/admin/auth/login', [
            'email'    => $admin->email,
            'password' => 'TestPass@2026',
        ])->assertStatus(403);
    }

    // ── Protected API Access ─────────────────────────────────────────

    public function test_admin_can_access_protected_api_after_login(): void
    {
        $admin = $this->createActiveAdmin();
        $token = $this->loginAndGetToken($admin);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/admin/stats/summary')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/admin/stats/summary')
            ->assertStatus(401);
    }

    public function test_frontend_user_token_cannot_access_admin_api(): void
    {
        $user = \App\Models\User::factory()->create(['membership_level' => 3]);
        $userToken = $user->createToken('user-login')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $userToken")
            ->getJson('/api/v1/admin/stats/summary')
            ->assertStatus(401);
    }

    public function test_admin_can_fetch_members_list(): void
    {
        $admin = $this->createActiveAdmin();
        $token = $this->loginAndGetToken($admin);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/admin/members')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_logout(): void
    {
        $admin = $this->createActiveAdmin();
        $token = $this->loginAndGetToken($admin);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/admin/auth/logout')
            ->assertOk();

        // Token should be invalidated after logout
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/admin/stats/summary')
            ->assertStatus(401);
    }
}
