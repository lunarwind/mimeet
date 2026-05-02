<?php

namespace Tests\Feature\Admin;

use App\Mail\TicketProcessedMail;
use App\Models\AdminUser;
use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * D.3 解耦版整合測試（用戶決策 Q1-Q9）：
 * - case #3 是核心驗證點：approve appeal 對 suspended user → ticket=resolved + user.status STAYS suspended
 * - 雙軌通知：active → 站內訊息；suspended → email
 *
 * Refs:
 * - docs/decisions/2026-05-01-check-suspended-decision.md
 * - Phase G report — 解耦設計核心
 */
class TicketProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): AdminUser
    {
        return AdminUser::create([
            'name' => 'Test Admin',
            'email' => 'admin-' . Str::random(6) . '@test.mimeet',
            'password' => Hash::make('TestPass@2026'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    private function adminBearer(AdminUser $admin): array
    {
        return ['Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken];
    }

    private function createUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'membership_level' => 1,
            'credit_score' => 60,
            'status' => 'active',
        ], $attrs));
    }

    private function createAppealReport(User $reporter, string $status = 'pending'): Report
    {
        return Report::create([
            'uuid' => Str::uuid()->toString(),
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reporter->id,
            'type' => 'appeal',
            'description' => '我認為停權有誤。',
            'status' => $status,
        ]);
    }

    private function createGenericReport(User $reporter, ?User $reportedUser = null, string $type = 'system_issue'): Report
    {
        return Report::create([
            'uuid' => Str::uuid()->toString(),
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser?->id,
            'type' => $type,
            'description' => '系統問題回報。',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function admin_get_ticket_detail_for_appeal_returns_appeal_info(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser(['status' => 'suspended', 'suspended_at' => now()]);
        $report = $this->createAppealReport($user);

        $res = $this->withHeaders($this->adminBearer($admin))
            ->getJson("/api/v1/admin/tickets/{$report->id}");

        $res->assertOk()
            ->assertJsonPath('data.type', 'appeal')
            ->assertJsonPath('data.reporter.status', 'suspended')
            ->assertJsonStructure([
                'data' => [
                    'id', 'type', 'status', 'description', 'reporter',
                    'appeal_info' => ['credit_score_history', 'received_reports', 'images'],
                ],
            ]);
    }

    /** @test */
    public function admin_get_ticket_detail_for_non_appeal_omits_appeal_info(): void
    {
        $admin = $this->createAdmin();
        $reporter = $this->createUser();
        $report = $this->createGenericReport($reporter);

        $res = $this->withHeaders($this->adminBearer($admin))
            ->getJson("/api/v1/admin/tickets/{$report->id}");

        $res->assertOk()
            ->assertJsonPath('data.type', 'system_issue')
            ->assertJsonMissing(['appeal_info']);
    }

    /** @test */
    public function resolve_appeal_for_suspended_user_decouples_user_status_and_emails(): void
    {
        // ── 核心解耦驗證 ──
        Mail::fake();
        $admin = $this->createAdmin();
        $user = $this->createUser([
            'status' => 'suspended',
            'suspended_at' => now(),
            'credit_score' => 0,
        ]);
        $report = $this->createAppealReport($user);

        $res = $this->withHeaders($this->adminBearer($admin))
            ->patchJson("/api/v1/admin/tickets/{$report->id}/status", [
                'status' => 'resolved',
                'admin_reply' => '審核通過。',
            ]);

        $res->assertOk();
        $report->refresh();
        $user->refresh();

        // ticket 變更
        $this->assertSame('resolved', $report->status);
        $this->assertSame('審核通過。', $report->resolution_note);
        $this->assertSame($admin->id, $report->resolved_by);

        // ⭐ 解耦核心：user.status **不變**
        $this->assertSame('suspended', $user->status, '解耦：ticket 處理不應變更 user.status');
        $this->assertSame(0, $user->credit_score, '解耦：ticket 處理不應變更 credit_score');

        // 通知走 email（suspended user）
        Mail::assertQueued(TicketProcessedMail::class, function ($mail) use ($report) {
            return $mail->ticket->id === $report->id
                && $mail->newStatus === 'resolved'
                && $mail->isAppeal === true;
        });
    }

    /** @test */
    public function resolve_appeal_for_active_user_uses_inapp_notification(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();
        // edge case: appeal exists but reporter is now active
        // (例如 admin 已先解停，再回頭處理 ticket)
        $user = $this->createUser(['status' => 'active']);
        $report = $this->createAppealReport($user);

        $this->withHeaders($this->adminBearer($admin))
            ->patchJson("/api/v1/admin/tickets/{$report->id}/status", [
                'status' => 'resolved',
                'admin_reply' => 'OK',
            ])
            ->assertOk();

        // active → 站內訊息，不寄 email
        Mail::assertNothingQueued();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'ticket_replied',
        ]);
    }

    /** @test */
    public function dismiss_appeal_for_suspended_user_emails_with_reject_template(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();
        $user = $this->createUser(['status' => 'auto_suspended', 'suspended_at' => now()]);
        $report = $this->createAppealReport($user);

        $this->withHeaders($this->adminBearer($admin))
            ->patchJson("/api/v1/admin/tickets/{$report->id}/status", [
                'status' => 'dismissed',
                'admin_reply' => '理由不充分。',
            ])
            ->assertOk();

        $report->refresh();
        $user->refresh();

        $this->assertSame('dismissed', $report->status);
        $this->assertSame('auto_suspended', $user->status, '解耦：reject 不變更 user.status');

        Mail::assertQueued(TicketProcessedMail::class, function ($mail) {
            return $mail->newStatus === 'dismissed' && $mail->isAppeal === true;
        });
    }

    /** @test */
    public function admin_reply_required_when_status_resolved(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser(['status' => 'suspended', 'suspended_at' => now()]);
        $report = $this->createAppealReport($user);

        $this->withHeaders($this->adminBearer($admin))
            ->patchJson("/api/v1/admin/tickets/{$report->id}/status", [
                'status' => 'resolved',
                // admin_reply missing
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function repeat_terminal_status_does_not_resend_notification(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();
        $user = $this->createUser(['status' => 'suspended', 'suspended_at' => now()]);
        $report = $this->createAppealReport($user, 'resolved');  // already resolved

        $this->withHeaders($this->adminBearer($admin))
            ->patchJson("/api/v1/admin/tickets/{$report->id}/status", [
                'status' => 'resolved',
                'admin_reply' => '重複設定。',
            ])
            ->assertOk();

        Mail::assertNothingQueued();
    }

    /** @test */
    public function notification_channel_uses_status_at_processing_time(): void
    {
        // 用戶當下狀態決定通知管道（race-safe snapshot）
        Mail::fake();
        $admin = $this->createAdmin();
        // user is active when admin processes — 走站內訊息（即使 ticket 是 appeal type）
        $user = $this->createUser(['status' => 'active']);
        $report = $this->createAppealReport($user);

        $this->withHeaders($this->adminBearer($admin))
            ->patchJson("/api/v1/admin/tickets/{$report->id}/status", [
                'status' => 'dismissed',
                'admin_reply' => 'X',
            ])
            ->assertOk();

        Mail::assertNothingQueued();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'ticket_replied',
        ]);
    }

    /** @test */
    public function reply_endpoint_accepts_message_field(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser();
        $report = $this->createGenericReport($user);

        $res = $this->withHeaders($this->adminBearer($admin))
            ->postJson("/api/v1/admin/tickets/{$report->id}/reply", [
                'message' => '管理員追加說明。',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('report_followups', [
            'report_id' => $report->id,
            'message' => '管理員追加說明。',
        ]);
    }

    /** @test */
    public function non_admin_cannot_get_ticket_detail(): void
    {
        $user = $this->createUser();
        $report = $this->createAppealReport($user);

        // No auth at all
        $this->getJson("/api/v1/admin/tickets/{$report->id}")
            ->assertStatus(401);

        // User token — admin guard rejects
        $userToken = $user->createToken('test')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer {$userToken}"])
            ->getJson("/api/v1/admin/tickets/{$report->id}")
            ->assertStatus(401);
    }
}
