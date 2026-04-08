<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GdprService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GdprTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'membership_level' => 2,
            'credit_score' => 60,
            'status' => 'active',
            'password' => Hash::make('testpassword'),
        ], $attrs));
    }

    public function test_user_can_request_account_deletion_with_correct_password(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/v1/me/delete-account', [
            'password' => 'testpassword',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending_deletion')
            ->assertJsonStructure(['data' => ['delete_at', 'message']]);

        $user->refresh();
        $this->assertEquals('pending_deletion', $user->status);
        $this->assertNotNull($user->delete_requested_at);
    }

    public function test_wrong_password_returns_422(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/v1/me/delete-account', [
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'PASSWORD_INCORRECT');
    }

    public function test_cannot_request_deletion_if_already_pending(): void
    {
        $user = $this->createUser([
            'status' => 'pending_deletion',
            'delete_requested_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/me/delete-account', [
            'password' => 'testpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'DELETION_PENDING');
    }

    public function test_user_can_cancel_deletion_request(): void
    {
        $user = $this->createUser([
            'status' => 'pending_deletion',
            'delete_requested_at' => now(),
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/me/delete-account');

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');

        $user->refresh();
        $this->assertEquals('active', $user->status);
        $this->assertNull($user->delete_requested_at);
    }

    public function test_cancel_without_pending_request_returns_422(): void
    {
        $user = $this->createUser(['status' => 'active']);

        $response = $this->actingAs($user)->deleteJson('/api/v1/me/delete-account');

        $response->assertStatus(422);
    }

    public function test_gdpr_command_anonymizes_users_after_7_days(): void
    {
        $user = $this->createUser([
            'status' => 'pending_deletion',
            'delete_requested_at' => now()->subDays(8),
        ]);

        $this->artisan('gdpr:process-deletions')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertEquals('deleted', $user->status);
        $this->assertEquals("deleted_{$user->id}@removed.mimeet", $user->email);
        $this->assertEquals('已刪除用戶', $user->nickname);
        $this->assertNull($user->phone);
        $this->assertNull($user->avatar_url);
    }

    public function test_gdpr_command_does_not_process_recent_requests(): void
    {
        $user = $this->createUser([
            'status' => 'pending_deletion',
            'delete_requested_at' => now()->subDays(3), // Only 3 days ago
        ]);
        $originalEmail = $user->email;

        $this->artisan('gdpr:process-deletions')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertEquals('pending_deletion', $user->status);
        $this->assertEquals($originalEmail, $user->email);
    }

    public function test_anonymized_user_password_is_randomized(): void
    {
        $user = $this->createUser([
            'status' => 'pending_deletion',
            'delete_requested_at' => now()->subDays(8),
        ]);

        $gdprService = app(GdprService::class);
        $gdprService->anonymizeUser($user);

        $user->refresh();
        // Password should be randomized — Hash::check with original password should fail
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('testpassword', $user->password));
        $this->assertEquals('deleted', $user->status);
    }
}
