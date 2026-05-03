<?php

namespace Tests\Feature;

use App\Models\CreditScoreHistory;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
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

    public function test_user_can_create_report(): void
    {
        $reporter = $this->createUser();
        $reported = $this->createUser();

        $response = $this->actingAs($reporter)->postJson('/api/v1/reports', [
            'reported_user_id' => $reported->id,
            'type' => 'harassment',
            'description' => '騷擾訊息',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['report' => ['id', 'uuid', 'status', 'type']]]);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reported->id,
            'status' => 'pending',
        ]);
    }

    public function test_reporter_loses_10_points(): void
    {
        $reporter = $this->createUser(['credit_score' => 60]);
        $reported = $this->createUser();

        $this->actingAs($reporter)->postJson('/api/v1/reports', [
            'reported_user_id' => $reported->id,
            'type' => 'harassment',
        ]);

        $reporter->refresh();
        $this->assertEquals(50, $reporter->credit_score);

        $this->assertDatabaseHas('credit_score_histories', [
            'user_id' => $reporter->id,
            'delta' => -10,
            'type' => 'report_submit',
        ]);
    }

    public function test_reported_user_loses_10_points(): void
    {
        $reporter = $this->createUser();
        $reported = $this->createUser(['credit_score' => 60]);

        $this->actingAs($reporter)->postJson('/api/v1/reports', [
            'reported_user_id' => $reported->id,
            'type' => 'scam',
        ]);

        $reported->refresh();
        $this->assertEquals(50, $reported->credit_score);

        $this->assertDatabaseHas('credit_score_histories', [
            'user_id' => $reported->id,
            'delta' => -10,
            'type' => 'report_submit',
        ]);
    }

    public function test_resolved_deducts_extra_5_from_reported(): void
    {
        $reporter = $this->createUser();
        $reported = $this->createUser(['credit_score' => 60]);
        $admin = $this->createUser();

        $report = Report::create([
            'uuid' => fake()->uuid(),
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reported->id,
            'type' => 'harassment',
            'status' => 'pending',
        ]);

        // Simulate initial deductions already applied
        $reported->update(['credit_score' => 50]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/tickets/{$report->id}/status", [
            'status' => 'resolved',
            'note' => '檢舉屬實',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.affected_scores.reported_change', -5);

        $reported->refresh();
        $this->assertEquals(45, $reported->credit_score);
    }

    public function test_dismissed_refunds_10_to_reporter(): void
    {
        $reporter = $this->createUser(['credit_score' => 50]); // after -10 deduction
        $reported = $this->createUser();
        $admin = $this->createUser();

        $report = Report::create([
            'uuid' => fake()->uuid(),
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reported->id,
            'type' => 'impersonation',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/tickets/{$report->id}/status", [
            'status' => 'dismissed',
            'note' => '檢舉不成立',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.affected_scores.reporter_change', 10);

        $reporter->refresh();
        $this->assertEquals(60, $reporter->credit_score);
    }

    public function test_cannot_report_yourself(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/api/v1/reports', [
            'reported_user_id' => $user->id,
            'type' => 'other',
        ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->postJson('/api/v1/reports', [
            'reported_user_id' => 1,
            'type' => 'other',
        ]);

        $response->assertStatus(401);
    }
}
