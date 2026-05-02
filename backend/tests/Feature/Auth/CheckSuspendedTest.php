<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D 方案整合測試：
 * 1) login 對 suspended/auto_suspended 仍回 200 + token + user.status（決策 1A）
 * 2) check.suspended middleware 對受保護端點回 403 ACCOUNT_SUSPENDED
 * 3) /auth/me、/auth/logout、/me/appeal、/me/appeal/current 4 條 whitelist 對停權者仍可達
 *
 * Refs: docs/decisions/2026-05-01-check-suspended-decision.md
 */
class CheckSuspendedTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'membership_level' => 1,
            'credit_score' => 60,
            'status' => 'active',
            'password' => Hash::make('Test1234!'),
        ], $attrs));
    }

    private function bearer(User $user, string $name = 'test'): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken($name)->plainTextToken];
    }

    /** @test */
    public function active_user_with_token_can_hit_protected_endpoint(): void
    {
        $user = $this->createUser();

        $this->withHeaders($this->bearer($user))
            ->getJson('/api/v1/users/me')
            ->assertOk();
    }

    /** @test */
    public function suspended_user_with_token_gets_403_on_protected_endpoint(): void
    {
        $user = $this->createUser(['status' => 'suspended', 'suspended_at' => now()]);

        $res = $this->withHeaders($this->bearer($user))
            ->getJson('/api/v1/users/me');

        $res->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_SUSPENDED')
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function suspended_user_with_token_can_post_appeal(): void
    {
        $user = $this->createUser(['status' => 'suspended', 'suspended_at' => now()]);

        // /me/appeal whitelisted via withoutMiddleware('check.suspended')
        $res = $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/me/appeal', ['reason' => '我覺得停權有誤，請審核我的情況。']);

        // AppealController returns 201 on success
        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['ticket_no', 'message']]);
    }

    /** @test */
    public function suspended_user_with_token_can_get_appeal_current(): void
    {
        $user = $this->createUser(['status' => 'suspended', 'suspended_at' => now()]);

        $this->withHeaders($this->bearer($user))
            ->getJson('/api/v1/me/appeal/current')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function suspended_user_with_token_can_get_auth_me(): void
    {
        $user = $this->createUser(['status' => 'suspended', 'suspended_at' => now()]);

        $this->withHeaders($this->bearer($user))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.status', 'suspended');
    }

    /** @test */
    public function suspended_user_with_token_can_logout(): void
    {
        $user = $this->createUser(['status' => 'suspended', 'suspended_at' => now()]);

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function auto_suspended_user_behaves_same_as_suspended(): void
    {
        $user = $this->createUser(['status' => 'auto_suspended', 'suspended_at' => now()]);

        // Protected endpoint blocked
        $this->withHeaders($this->bearer($user))
            ->getJson('/api/v1/users/me')
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_SUSPENDED');

        // Whitelist endpoint reachable
        $this->withHeaders($this->bearer($user))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.status', 'auto_suspended');
    }

    /** @test */
    public function suspended_user_login_returns_200_with_token_and_status(): void
    {
        $this->createUser([
            'email' => 'suspended-login@test.mimeet',
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended-login@test.mimeet',
            'password' => 'Test1234!',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'LOGIN_SUCCESS')
            ->assertJsonPath('data.user.status', 'suspended')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email', 'status']]]);
    }

    /** @test */
    public function active_user_login_returns_200_with_token_and_status(): void
    {
        $this->createUser([
            'email' => 'active-login@test.mimeet',
            'status' => 'active',
        ]);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'active-login@test.mimeet',
            'password' => 'Test1234!',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'LOGIN_SUCCESS')
            ->assertJsonPath('data.user.status', 'active');
    }

    /** @test */
    public function wrong_password_login_returns_401_invalid_credentials(): void
    {
        $this->createUser(['email' => 'wrongpw@test.mimeet']);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrongpw@test.mimeet',
            'password' => 'WrongPassword',
        ]);

        $res->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    /** @test */
    public function unauthenticated_request_to_protected_endpoint_returns_401(): void
    {
        // No bearer token at all → auth:sanctum returns 401 BEFORE check.suspended runs
        $this->getJson('/api/v1/users/me')->assertStatus(401);
    }
}
