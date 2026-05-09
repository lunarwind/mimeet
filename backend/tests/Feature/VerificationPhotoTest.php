<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VerificationPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function femaleUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'gender' => 'female',
            'membership_level' => 1.0,
        ], $overrides));
    }

    private function adminHeaders(string $role = 'super_admin'): array
    {
        $admin = AdminUser::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
        $token = $admin->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_request_blocks_when_user_has_pending_review(): void
    {
        $user = $this->femaleUser();
        Sanctum::actingAs($user);

        UserVerification::create([
            'user_id' => $user->id,
            'random_code' => 'OLD001',
            'photo_url' => 'https://example.com/photo.jpg',
            'status' => 'pending_review',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/me/verification-photo/request');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VERIFICATION_PENDING_REVIEW');

        $this->assertDatabaseMissing('user_verifications', [
            'user_id' => $user->id,
            'status' => 'pending_code',
        ]);
    }

    public function test_upload_blocks_when_user_has_pending_review(): void
    {
        $user = $this->femaleUser();
        Sanctum::actingAs($user);

        UserVerification::create([
            'user_id' => $user->id,
            'random_code' => 'OLD001',
            'photo_url' => 'https://example.com/old.jpg',
            'status' => 'pending_review',
            'expires_at' => now()->addMinutes(10),
        ]);

        UserVerification::create([
            'user_id' => $user->id,
            'random_code' => 'NEW001',
            'status' => 'pending_code',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/me/verification-photo/upload', [
            'photo_url' => 'https://example.com/new.jpg',
            'random_code' => 'NEW001',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VERIFICATION_PENDING_REVIEW');
    }

    public function test_request_succeeds_after_rejection(): void
    {
        $user = $this->femaleUser();
        Sanctum::actingAs($user);

        UserVerification::create([
            'user_id' => $user->id,
            'random_code' => 'OLD001',
            'photo_url' => 'https://example.com/old.jpg',
            'status' => 'rejected',
            'reject_reason' => '照片模糊',
            'expires_at' => now()->subMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/me/verification-photo/request');

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_verifications', [
            'user_id' => $user->id,
            'status' => 'pending_code',
        ]);
    }

    public function test_request_blocks_when_already_verified(): void
    {
        $user = $this->femaleUser(['membership_level' => 1.5]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/me/verification-photo/request');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_VERIFIED');
    }

    public function test_request_blocks_for_non_female_user(): void
    {
        $user = User::factory()->create(['gender' => 'male', 'membership_level' => 1.0]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/me/verification-photo/request');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_ELIGIBLE');
    }

    public function test_admin_review_blocks_when_status_not_pending_review(): void
    {
        $user = $this->femaleUser();

        $verification = UserVerification::create([
            'user_id' => $user->id,
            'random_code' => 'OLD001',
            'photo_url' => 'https://example.com/photo.jpg',
            'status' => 'approved',
            'expires_at' => now()->addMinutes(10),
            'reviewed_at' => now()->subDay(),
        ]);

        $response = $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/v1/admin/verifications/{$verification->id}", [
                'result' => 'approved',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VERIFICATION_ALREADY_REVIEWED');
    }

    public function test_status_endpoint_prefers_pending_review_over_newer_records(): void
    {
        // Simulates dirty data from the original bug:
        // user has pending_review (older) and pending_code (newer)
        $user = $this->femaleUser();
        Sanctum::actingAs($user);

        UserVerification::create([
            'user_id' => $user->id,
            'random_code' => 'OLD001',
            'photo_url' => 'https://example.com/old.jpg',
            'status' => 'pending_review',
            'expires_at' => now()->addMinutes(10),
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        UserVerification::create([
            'user_id' => $user->id,
            'random_code' => 'NEW001',
            'status' => 'pending_code',
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/me/verification-photo/status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending_review');
    }

    public function test_repeated_request_calls_produce_only_one_pending_code(): void
    {
        // Sequential simulation of repeated request calls.
        // Real concurrent stress should be tested at staging.
        $user = $this->femaleUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/verification-photo/request')->assertOk();
        $this->postJson('/api/v1/me/verification-photo/request')->assertOk();

        $this->assertEquals(1, UserVerification::where('user_id', $user->id)
            ->where('status', 'pending_code')
            ->count());
        $this->assertEquals(1, UserVerification::where('user_id', $user->id)
            ->where('status', 'expired')
            ->count());
    }
}
