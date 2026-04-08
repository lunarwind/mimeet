<?php

namespace Tests\Feature;

use App\Models\DateInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DateTest extends TestCase
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

    private function createInvitation(User $inviter, User $invitee, array $overrides = []): DateInvitation
    {
        return DateInvitation::create(array_merge([
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'date_time' => now()->addHour(),
            'latitude' => 25.0340,
            'longitude' => 121.5645,
            'qr_token' => bin2hex(random_bytes(32)),
            'status' => 'pending',
            'expires_at' => now()->addHour()->addMinutes(30),
            'created_at' => now(),
        ], $overrides));
    }

    public function test_can_create_date_invitation(): void
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();

        $response = $this->actingAs($inviter)->postJson('/api/v1/dates', [
            'invitee_id' => $invitee->id,
            'date_time' => now()->addDay()->toISOString(),
            'location_name' => '台北101',
            'latitude' => 25.0340,
            'longitude' => 121.5645,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['invitation' => ['id', 'qr_token', 'status']]]);

        $this->assertDatabaseHas('date_invitations', [
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'status' => 'pending',
        ]);
    }

    public function test_invitee_can_accept(): void
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();
        $inv = $this->createInvitation($inviter, $invitee);

        $response = $this->actingAs($invitee)->patchJson("/api/v1/dates/{$inv->id}/accept");

        $response->assertOk()
            ->assertJsonPath('data.invitation.status', 'accepted');
    }

    public function test_invitee_can_decline(): void
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();
        $inv = $this->createInvitation($inviter, $invitee);

        $response = $this->actingAs($invitee)->patchJson("/api/v1/dates/{$inv->id}/decline");

        $response->assertOk()
            ->assertJsonPath('data.invitation.status', 'cancelled');
    }

    public function test_expired_token_returns_422(): void
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();
        $inv = $this->createInvitation($inviter, $invitee, [
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($inviter)->postJson('/api/v1/dates/verify', [
            'token' => $inv->qr_token,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'TOKEN_EXPIRED');
    }

    public function test_gps_within_500m_awards_5_points(): void
    {
        $inviter = $this->createUser(['credit_score' => 50]);
        $invitee = $this->createUser(['credit_score' => 50]);
        $inv = $this->createInvitation($inviter, $invitee, [
            'status' => 'accepted',
            'latitude' => 25.0340,
            'longitude' => 121.5645,
        ]);

        // Inviter scans with GPS within 500m
        $this->actingAs($inviter)->postJson('/api/v1/dates/verify', [
            'token' => $inv->qr_token,
            'latitude' => 25.0341,
            'longitude' => 121.5646,
        ]);

        // Invitee scans with GPS within 500m
        $response = $this->actingAs($invitee)->postJson('/api/v1/dates/verify', [
            'token' => $inv->qr_token,
            'latitude' => 25.0342,
            'longitude' => 121.5647,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.gps_passed', true)
            ->assertJsonPath('data.score_awarded', 5);

        $inviter->refresh();
        $invitee->refresh();
        $this->assertEquals(55, $inviter->credit_score);
        $this->assertEquals(55, $invitee->credit_score);
    }

    public function test_no_gps_awards_2_points(): void
    {
        $inviter = $this->createUser(['credit_score' => 50]);
        $invitee = $this->createUser(['credit_score' => 50]);
        $inv = $this->createInvitation($inviter, $invitee, [
            'status' => 'accepted',
        ]);

        // Both scan without GPS
        $this->actingAs($inviter)->postJson('/api/v1/dates/verify', [
            'token' => $inv->qr_token,
        ]);

        $response = $this->actingAs($invitee)->postJson('/api/v1/dates/verify', [
            'token' => $inv->qr_token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.gps_passed', false)
            ->assertJsonPath('data.score_awarded', 2);

        $inviter->refresh();
        $invitee->refresh();
        $this->assertEquals(52, $inviter->credit_score);
        $this->assertEquals(52, $invitee->credit_score);
    }

    public function test_same_pair_24h_no_extra_score(): void
    {
        $inviter = $this->createUser(['credit_score' => 50]);
        $invitee = $this->createUser(['credit_score' => 50]);

        // Simulate cooldown already set
        $minId = min($inviter->id, $invitee->id);
        $maxId = max($inviter->id, $invitee->id);
        Cache::put("date_score:{$minId}:{$maxId}", 1, 86400);

        $inv = $this->createInvitation($inviter, $invitee, [
            'status' => 'accepted',
        ]);

        $this->actingAs($inviter)->postJson('/api/v1/dates/verify', [
            'token' => $inv->qr_token,
        ]);

        $response = $this->actingAs($invitee)->postJson('/api/v1/dates/verify', [
            'token' => $inv->qr_token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.score_awarded', 0);

        // Score should remain unchanged
        $inviter->refresh();
        $this->assertEquals(50, $inviter->credit_score);
    }

    public function test_non_participant_gets_403(): void
    {
        $inviter = $this->createUser();
        $invitee = $this->createUser();
        $outsider = $this->createUser();
        $inv = $this->createInvitation($inviter, $invitee);

        $response = $this->actingAs($outsider)->postJson('/api/v1/dates/verify', [
            'token' => $inv->qr_token,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'NOT_PARTICIPANT');
    }
}
