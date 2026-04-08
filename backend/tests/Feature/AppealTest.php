<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Services\CreditScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppealTest extends TestCase
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

    public function test_suspended_user_can_submit_appeal(): void
    {
        $user = $this->createUser([
            'status' => 'auto_suspended',
            'credit_score' => 0,
            'suspended_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/me/appeal', [
            'reason' => '我認為停權有誤，理由如下...',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['ticket_no', 'message']]);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $user->id,
            'type' => 'appeal',
            'status' => 'pending',
        ]);
    }

    public function test_non_suspended_user_cannot_submit_appeal(): void
    {
        $user = $this->createUser(['status' => 'active']);

        $response = $this->actingAs($user)->postJson('/api/v1/me/appeal', [
            'reason' => 'test',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_SUSPENDED');
    }

    public function test_duplicate_appeal_in_same_suspension_returns_422(): void
    {
        $user = $this->createUser([
            'status' => 'auto_suspended',
            'credit_score' => 0,
            'suspended_at' => now()->subHour(),
        ]);

        // First appeal
        $this->actingAs($user)->postJson('/api/v1/me/appeal', ['reason' => 'First']);

        // Second appeal
        $response = $this->actingAs($user)->postJson('/api/v1/me/appeal', ['reason' => 'Second']);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'APPEAL_EXISTS');
    }

    public function test_get_current_appeal_returns_null_when_none(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/me/appeal/current');

        $response->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_approve_appeal_with_score_below_30_returns_422(): void
    {
        $admin = $this->createUser();
        $user = $this->createUser(['status' => 'auto_suspended', 'credit_score' => 0]);

        $report = Report::create([
            'uuid' => fake()->uuid(),
            'reporter_id' => $user->id,
            'reported_user_id' => $user->id,
            'type' => 'appeal',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/tickets/{$report->id}/status", [
            'action' => 'approve_appeal',
            'restore_score' => 10,
            'admin_reply' => 'test',
        ]);

        $response->assertStatus(422);
    }

    public function test_approve_appeal_restores_user_status_to_active(): void
    {
        $admin = $this->createUser();
        $user = $this->createUser(['status' => 'auto_suspended', 'credit_score' => 0]);

        $report = Report::create([
            'uuid' => fake()->uuid(),
            'reporter_id' => $user->id,
            'reported_user_id' => $user->id,
            'type' => 'appeal',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/tickets/{$report->id}/status", [
            'action' => 'approve_appeal',
            'restore_score' => 35,
            'admin_reply' => '審核通過',
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertEquals(35, $user->credit_score);
        $this->assertEquals('active', $user->status);
    }

    public function test_reject_appeal_keeps_user_suspended(): void
    {
        $admin = $this->createUser();
        $user = $this->createUser(['status' => 'auto_suspended', 'credit_score' => 0]);

        $report = Report::create([
            'uuid' => fake()->uuid(),
            'reporter_id' => $user->id,
            'reported_user_id' => $user->id,
            'type' => 'appeal',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/tickets/{$report->id}/status", [
            'action' => 'reject_appeal',
            'admin_reply' => '理由不充分',
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertEquals('auto_suspended', $user->status);
    }

    public function test_credit_score_zero_triggers_auto_suspend(): void
    {
        $user = $this->createUser(['credit_score' => 10, 'status' => 'active']);

        CreditScoreService::adjust($user, -10, 'test', 'test deduction');

        $user->refresh();
        $this->assertEquals(0, $user->credit_score);
        $this->assertEquals('auto_suspended', $user->status);
        $this->assertNotNull($user->suspended_at);
    }

    public function test_credit_score_above_30_triggers_auto_restore(): void
    {
        $user = $this->createUser(['credit_score' => 0, 'status' => 'auto_suspended']);

        CreditScoreService::adjust($user, 35, 'test', 'restore');

        $user->refresh();
        $this->assertEquals(35, $user->credit_score);
        $this->assertEquals('active', $user->status);
    }
}
