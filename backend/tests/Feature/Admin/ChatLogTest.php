<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\GdprService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatLogTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(string $role = 'super_admin'): AdminUser
    {
        return AdminUser::factory()->create(['role' => $role]);
    }

    private function withAdminAuth(AdminUser $admin): self
    {
        $token = $admin->createToken('test')->plainTextToken;
        return $this->withHeaders(['Authorization' => "Bearer {$token}"]);
    }

    private function createConversationWithMessages(User $userA, User $userB, int $count = 5): Conversation
    {
        $minId = min($userA->id, $userB->id);
        $maxId = max($userA->id, $userB->id);

        $conv = Conversation::create([
            'uuid' => Str::uuid()->toString(),
            'user_a_id' => $minId,
            'user_b_id' => $maxId,
            'last_message_at' => now(),
        ]);

        $msg = null;
        for ($i = 0; $i < $count; $i++) {
            $msg = Message::create([
                'uuid' => Str::uuid()->toString(),
                'conversation_id' => $conv->id,
                'sender_id' => $i % 2 === 0 ? $userA->id : $userB->id,
                'content' => "測試訊息 #{$i} 含關鍵字測試",
                'type' => 'text',
                'sent_at' => now()->subMinutes($count - $i),
            ]);
        }

        $conv->update(['last_message_id' => $msg?->id]);

        return $conv;
    }

    /**
     * 建立一筆 recalled 訊息（保留 content 在 DB，符合 ChatService::recallMessage 行為）
     */
    private function seedRecalledMessage(Conversation $conv, User $sender, string $content, ?\Carbon\Carbon $recalledAt = null): Message
    {
        return Message::create([
            'uuid' => Str::uuid()->toString(),
            'conversation_id' => $conv->id,
            'sender_id' => $sender->id,
            'content' => $content,
            'type' => 'text',
            'is_recalled' => true,
            'recalled_at' => $recalledAt ?? now(),
            'sent_at' => $recalledAt ? $recalledAt->copy()->subMinutes(2) : now()->subMinutes(2),
        ]);
    }

    public function test_super_admin_can_search_chat_logs(): void
    {
        $admin = $this->createAdmin();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createConversationWithMessages($userA, $userB);

        $response = $this->withAdminAuth($admin)->getJson('/api/v1/admin/chat-logs/search?keyword=關鍵字');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta' => ['total', 'page']]);
    }

    public function test_search_requires_keyword_min_2_chars(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withAdminAuth($admin)->getJson('/api/v1/admin/chat-logs/search?keyword=a');
        $response->assertStatus(422);
    }

    public function test_conversations_returns_messages_between_two_users(): void
    {
        $admin = $this->createAdmin();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createConversationWithMessages($userA, $userB, 3);

        $response = $this->withAdminAuth($admin)->getJson(
            "/api/v1/admin/chat-logs/conversations?user_a={$userA->id}&user_b={$userB->id}"
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.messages');
    }

    // ── Option C 分級權限：recalled 訊息可見度 ──────────────────────────

    public function test_super_admin_sees_recalled_content_in_retention_period(): void
    {
        $superAdmin = $this->createAdmin('super_admin');
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $minId = min($userA->id, $userB->id);
        $maxId = max($userA->id, $userB->id);
        $conv = Conversation::create([
            'uuid' => Str::uuid()->toString(),
            'user_a_id' => $minId,
            'user_b_id' => $maxId,
            'last_message_at' => now(),
        ]);
        $this->seedRecalledMessage($conv, $userA, '收回前的原文內容 ABCXYZ');

        $response = $this->withAdminAuth($superAdmin)->getJson(
            "/api/v1/admin/chat-logs/conversations?user_a={$userA->id}&user_b={$userB->id}"
        );

        $response->assertOk();
        $messages = $response->json('data.messages');
        $this->assertSame('收回前的原文內容 ABCXYZ', $messages[0]['content']);
        $this->assertTrue($messages[0]['is_recalled']);
        $this->assertTrue($messages[0]['is_content_visible']);
        $this->assertNotNull($messages[0]['recalled_at']);
    }

    public function test_regular_admin_sees_placeholder_for_recalled_content(): void
    {
        $admin = $this->createAdmin('admin');
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $minId = min($userA->id, $userB->id);
        $maxId = max($userA->id, $userB->id);
        $conv = Conversation::create([
            'uuid' => Str::uuid()->toString(),
            'user_a_id' => $minId,
            'user_b_id' => $maxId,
            'last_message_at' => now(),
        ]);
        $this->seedRecalledMessage($conv, $userA, '這是秘密訊息，admin 不應看到');

        $response = $this->withAdminAuth($admin)->getJson(
            "/api/v1/admin/chat-logs/conversations?user_a={$userA->id}&user_b={$userB->id}"
        );

        $response->assertOk();
        $messages = $response->json('data.messages');
        $this->assertNull($messages[0]['content']);
        $this->assertTrue($messages[0]['is_recalled']);
        $this->assertFalse($messages[0]['is_content_visible']);
    }

    public function test_super_admin_search_includes_recalled_by_keyword(): void
    {
        $superAdmin = $this->createAdmin('super_admin');
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $minId = min($userA->id, $userB->id);
        $maxId = max($userA->id, $userB->id);
        $conv = Conversation::create([
            'uuid' => Str::uuid()->toString(),
            'user_a_id' => $minId,
            'user_b_id' => $maxId,
            'last_message_at' => now(),
        ]);
        $this->seedRecalledMessage($conv, $userA, '機密關鍵詞 SUPERSECRET 出現過');

        $response = $this->withAdminAuth($superAdmin)->getJson(
            '/api/v1/admin/chat-logs/search?keyword=SUPERSECRET'
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['is_recalled']);
        $this->assertTrue($data[0]['is_content_visible']);
        $this->assertSame('機密關鍵詞 SUPERSECRET 出現過', $data[0]['content']);
    }

    public function test_regular_admin_search_excludes_recalled(): void
    {
        $admin = $this->createAdmin('admin');
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $minId = min($userA->id, $userB->id);
        $maxId = max($userA->id, $userB->id);
        $conv = Conversation::create([
            'uuid' => Str::uuid()->toString(),
            'user_a_id' => $minId,
            'user_b_id' => $maxId,
            'last_message_at' => now(),
        ]);
        // 唯一一筆訊息：recalled、含關鍵詞
        $this->seedRecalledMessage($conv, $userA, '機密關鍵詞 SUPERSECRET 出現過');

        $response = $this->withAdminAuth($admin)->getJson(
            '/api/v1/admin/chat-logs/search?keyword=SUPERSECRET'
        );

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(0, $data, 'admin 不應搜得到 recalled 訊息');
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_super_admin_view_non_recalled_does_not_write_audit_log(): void
    {
        // Q3 守護：只有「實際看到 recalled 原文」才寫稽核軌跡；一般查詢不該留痕跡
        $superAdmin = $this->createAdmin('super_admin');
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createConversationWithMessages($userA, $userB, 3);

        $this->withAdminAuth($superAdmin)->getJson(
            "/api/v1/admin/chat-logs/conversations?user_a={$userA->id}&user_b={$userB->id}"
        )->assertOk();

        $logCount = \App\Models\AdminOperationLog::where('admin_id', $superAdmin->id)
            ->where('action', 'like', 'chat_logs_%recalled%')
            ->count();

        $this->assertSame(0, $logCount, 'super_admin 查詢無 recalled 訊息的對話時，不應寫入 audit log');
    }

    public function test_super_admin_member_list_with_recalled_preview_writes_audit_log(): void
    {
        // Q4 守護：memberChatLogs 對話清單路徑也應覆蓋 audit
        $superAdmin = $this->createAdmin('super_admin');
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $minId = min($userA->id, $userB->id);
        $maxId = max($userA->id, $userB->id);
        $conv = Conversation::create([
            'uuid' => Str::uuid()->toString(),
            'user_a_id' => $minId,
            'user_b_id' => $maxId,
            'last_message_at' => now(),
        ]);
        $recalled = $this->seedRecalledMessage($conv, $userA, 'last_message preview 也屬於 recalled 原文');
        $conv->update(['last_message_id' => $recalled->id]);

        $this->withAdminAuth($superAdmin)->getJson(
            "/api/v1/admin/members/{$userA->id}/chat-logs"
        )->assertOk();

        $log = \App\Models\AdminOperationLog::where('admin_id', $superAdmin->id)
            ->where('action', 'chat_logs_member_list_recalled_preview')
            ->first();

        $this->assertNotNull($log, '對話清單中 last_message 為 recalled 時也應寫入 audit log');
        $this->assertContains($conv->id, $log->request_summary['recalled_preview_conversation_ids'] ?? []);
    }

    public function test_super_admin_view_recalled_writes_audit_log(): void
    {
        $superAdmin = $this->createAdmin('super_admin');
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $minId = min($userA->id, $userB->id);
        $maxId = max($userA->id, $userB->id);
        $conv = Conversation::create([
            'uuid' => Str::uuid()->toString(),
            'user_a_id' => $minId,
            'user_b_id' => $maxId,
            'last_message_at' => now(),
        ]);
        $this->seedRecalledMessage($conv, $userA, '稽核軌跡測試訊息');

        $this->withAdminAuth($superAdmin)->getJson(
            "/api/v1/admin/chat-logs/conversations?user_a={$userA->id}&user_b={$userB->id}"
        )->assertOk();

        $log = \App\Models\AdminOperationLog::where('admin_id', $superAdmin->id)
            ->where('action', 'chat_logs_view_recalled')
            ->first();

        $this->assertNotNull($log, 'super_admin 看到 recalled 訊息應寫入 admin_operation_logs');
        $this->assertSame('conversation', $log->resource_type);
        $this->assertTrue($log->request_summary['viewed_recalled_content'] ?? false);
        $this->assertSame(1, $log->request_summary['recalled_message_count'] ?? 0);
    }

    // ── Phase 2 銷毀：超過 retention 期的 recalled 訊息物理刪除 ─────────

    public function test_purge_old_recalled_messages_after_retention(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $minId = min($userA->id, $userB->id);
        $maxId = max($userA->id, $userB->id);
        $conv = Conversation::create([
            'uuid' => Str::uuid()->toString(),
            'user_a_id' => $minId,
            'user_b_id' => $maxId,
            'last_message_at' => now(),
        ]);

        $old = $this->seedRecalledMessage($conv, $userA, '200 天前收回的訊息', now()->subDays(200));
        $recent = $this->seedRecalledMessage($conv, $userB, '100 天前收回的訊息', now()->subDays(100));
        $stillActive = Message::create([
            'uuid' => Str::uuid()->toString(),
            'conversation_id' => $conv->id,
            'sender_id' => $userA->id,
            'content' => '正常訊息',
            'type' => 'text',
            'sent_at' => now()->subDays(200),
            'is_recalled' => false,
        ]);

        $gdpr = app(GdprService::class);
        $purged = $gdpr->purgeOldRecalledMessages(180);

        $this->assertSame(1, $purged, '只有 200 天前的 recalled 訊息應被 forceDelete');
        $this->assertNull(Message::find($old->id), '200 天前的 recalled 訊息應已刪除');
        $this->assertNotNull(Message::find($recent->id), '100 天前的 recalled 訊息應保留');
        $this->assertNotNull(Message::find($stillActive->id), '未收回訊息應保留');
    }

    public function test_export_returns_csv_content_type(): void
    {
        $admin = $this->createAdmin();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createConversationWithMessages($userA, $userB, 2);

        $response = $this->withAdminAuth($admin)->get(
            "/api/v1/admin/chat-logs/export?user_a={$userA->id}&user_b={$userB->id}"
        );

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_member_chat_logs_returns_conversation_list(): void
    {
        $admin = $this->createAdmin();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        $this->createConversationWithMessages($userA, $userB, 3);
        $this->createConversationWithMessages($userA, $userC, 2);

        $response = $this->withAdminAuth($admin)->getJson(
            "/api/v1/admin/members/{$userA->id}/chat-logs"
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/admin/chat-logs/search?keyword=test');
        $response->assertStatus(401);
    }

    public function test_conversations_not_found_returns_404(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withAdminAuth($admin)->getJson(
            '/api/v1/admin/chat-logs/conversations?user_a=99999&user_b=99998'
        );

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'CONVERSATION_NOT_FOUND');
    }
}
