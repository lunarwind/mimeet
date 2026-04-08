<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyTest extends TestCase
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

    public function test_user_can_get_privacy_settings(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/me/privacy');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'show_online_status',
                'allow_profile_visits',
                'show_in_search',
                'show_last_active',
                'allow_stranger_message',
            ]]);
    }

    public function test_default_privacy_settings_are_all_true(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/me/privacy');

        $data = $response->json('data');
        $this->assertTrue($data['show_online_status']);
        $this->assertTrue($data['allow_profile_visits']);
        $this->assertTrue($data['show_in_search']);
        $this->assertTrue($data['show_last_active']);
        $this->assertTrue($data['allow_stranger_message']);
    }

    public function test_user_can_update_single_privacy_key(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->patchJson('/api/v1/me/privacy', [
            'key' => 'show_in_search',
            'value' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.key', 'show_in_search')
            ->assertJsonPath('data.value', false);

        // Verify persisted
        $user->refresh();
        $this->assertFalse($user->privacy_settings['show_in_search']);
        // Other settings should remain true
        $this->assertTrue($user->privacy_settings['show_online_status']);
    }

    public function test_invalid_key_returns_422(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->patchJson('/api/v1/me/privacy', [
            'key' => 'invalid_key',
            'value' => false,
        ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/me/privacy');
        $response->assertStatus(401);
    }
}
