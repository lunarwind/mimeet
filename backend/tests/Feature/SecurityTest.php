<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'membership_level' => 2,
            'credit_score' => 60,
            'status' => 'active',
        ], $attrs));
    }

    // ─── IDOR Tests ──────────────────────────────────────────

    public function test_idor_accessing_another_users_conversation_returns_403(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        $userC = $this->createUser();

        $conv = Conversation::create([
            'uuid' => fake()->uuid(),
            'user_a_id' => min($userA->id, $userB->id),
            'user_b_id' => max($userA->id, $userB->id),
            'last_message_at' => now(),
        ]);

        // userC tries to access userA-userB conversation messages
        $response = $this->actingAs($userC)->getJson("/api/v1/chats/{$conv->id}/messages");
        $response->assertStatus(403);
    }

    public function test_idor_sending_message_to_another_users_conversation_returns_403(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        $userC = $this->createUser(['membership_level' => 2]);

        $conv = Conversation::create([
            'uuid' => fake()->uuid(),
            'user_a_id' => min($userA->id, $userB->id),
            'user_b_id' => max($userA->id, $userB->id),
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($userC)->postJson("/api/v1/chats/{$conv->id}/messages", [
            'content' => 'Trying to sneak in',
        ]);

        $response->assertStatus(403);
    }

    public function test_idor_marking_read_on_another_users_conversation_returns_403(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        $userC = $this->createUser();

        $conv = Conversation::create([
            'uuid' => fake()->uuid(),
            'user_a_id' => min($userA->id, $userB->id),
            'user_b_id' => max($userA->id, $userB->id),
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($userC)->patchJson("/api/v1/chats/{$conv->id}/read");
        $response->assertStatus(403);
    }

    // ─── Auth Bypass Tests ───────────────────────────────────

    public function test_auth_bypass_chats_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/chats');
        $response->assertStatus(401);
    }

    public function test_auth_bypass_user_profile_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/users/me');
        $response->assertStatus(401);
    }

    public function test_auth_bypass_reports_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/reports');
        $response->assertStatus(401);
    }

    public function test_auth_bypass_dates_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/dates');
        $response->assertStatus(401);
    }

    public function test_auth_bypass_notifications_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/notifications');
        $response->assertStatus(401);
    }

    // ─── Rate Limiting Tests ─────────────────────────────────

    public function test_rate_limiting_login_returns_429_after_too_many_attempts(): void
    {
        // The login endpoint is throttled to 5 attempts per minute
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // The 6th attempt should be rate limited
        $response->assertStatus(429);
    }
}
