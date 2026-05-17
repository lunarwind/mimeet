<?php

namespace Tests\Feature\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F19 訊息回收 — 過去 0 覆蓋，本檔為首次自動化測試。
 *
 * 業務規則（API-001 §4.1.5、ChatService::recallMessage）：
 *  - 僅 sender 本人可回收
 *  - 訊息需在 5 分鐘內（now() - sent_at <= 300s）
 *  - 訊息尚未被對方讀取（is_read = false）
 *  - 僅付費會員（membership_level >= 3）可用；路由掛 membership:3
 *  - 成功後 is_recalled=true、recalled_at=now()；content 保留在 DB（供 super_admin 稽核）
 */
class RecallMessageTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'membership_level' => 3,
            'credit_score' => 60,
            'status' => 'active',
        ], $attrs));
    }

    private function setupPair(int $senderLevel = 3): array
    {
        $sender = $this->createUser(['membership_level' => $senderLevel]);
        $receiver = $this->createUser(['membership_level' => 2]);

        $minId = min($sender->id, $receiver->id);
        $maxId = max($sender->id, $receiver->id);
        $conv = Conversation::create([
            'uuid' => Str::uuid()->toString(),
            'user_a_id' => $minId,
            'user_b_id' => $maxId,
            'last_message_at' => now(),
        ]);

        return [$sender, $receiver, $conv];
    }

    private function seedMessage(Conversation $conv, User $sender, array $overrides = []): Message
    {
        return Message::create(array_merge([
            'uuid' => Str::uuid()->toString(),
            'conversation_id' => $conv->id,
            'sender_id' => $sender->id,
            'content' => '收回前的原始訊息內容',
            'type' => 'text',
            'sent_at' => now(),
            'is_read' => false,
        ], $overrides));
    }

    public function test_paid_member_can_recall_unread_message_within_5_minutes(): void
    {
        [$sender, , $conv] = $this->setupPair(senderLevel: 3);
        $msg = $this->seedMessage($conv, $sender);

        $response = $this->actingAs($sender)->deleteJson(
            "/api/v1/chats/{$conv->id}/messages/{$msg->id}"
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message_id', $msg->id);

        $fresh = Message::find($msg->id);
        $this->assertTrue((bool) $fresh->is_recalled);
        $this->assertNotNull($fresh->recalled_at);
    }

    public function test_recall_after_5_minutes_returns_422(): void
    {
        [$sender, , $conv] = $this->setupPair(senderLevel: 3);
        $msg = $this->seedMessage($conv, $sender, [
            'sent_at' => now()->subMinutes(6),
        ]);

        $response = $this->actingAs($sender)->deleteJson(
            "/api/v1/chats/{$conv->id}/messages/{$msg->id}"
        );

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'RECALL_DENIED');

        $this->assertFalse((bool) Message::find($msg->id)->is_recalled);
    }

    public function test_recall_already_read_message_returns_422(): void
    {
        [$sender, , $conv] = $this->setupPair(senderLevel: 3);
        $msg = $this->seedMessage($conv, $sender, [
            'is_read' => true,
            'read_at' => now(),
        ]);

        $response = $this->actingAs($sender)->deleteJson(
            "/api/v1/chats/{$conv->id}/messages/{$msg->id}"
        );

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'RECALL_DENIED');
    }

    public function test_recall_by_non_sender_returns_422(): void
    {
        [$sender, $receiver, $conv] = $this->setupPair(senderLevel: 3);
        // receiver 也升 Lv3，才能經過 membership:3 中介層；用 update 避免重建
        $receiver->forceFill(['membership_level' => 3])->save();

        $msg = $this->seedMessage($conv, $sender);

        $response = $this->actingAs($receiver)->deleteJson(
            "/api/v1/chats/{$conv->id}/messages/{$msg->id}"
        );

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'RECALL_DENIED');

        $this->assertFalse((bool) Message::find($msg->id)->is_recalled);
    }

    public function test_recall_by_non_paid_member_returns_403(): void
    {
        [$sender, , $conv] = $this->setupPair(senderLevel: 2);  // Lv2 不夠
        $msg = $this->seedMessage($conv, $sender);

        $response = $this->actingAs($sender)->deleteJson(
            "/api/v1/chats/{$conv->id}/messages/{$msg->id}"
        );

        $response->assertStatus(403)
            ->assertJsonPath('code', 'MEMBERSHIP_REQUIRED');
    }

    public function test_recalled_message_preserves_content_in_db(): void
    {
        [$sender, , $conv] = $this->setupPair(senderLevel: 3);
        $msg = $this->seedMessage($conv, $sender, [
            'content' => '這段原文應在 DB 保留供 super_admin 在 retention 期內稽核',
        ]);

        $this->actingAs($sender)->deleteJson(
            "/api/v1/chats/{$conv->id}/messages/{$msg->id}"
        )->assertOk();

        // 直接讀 DB，不經 API（API 對一般用戶會 null 化）
        $fresh = Message::find($msg->id);
        $this->assertTrue((bool) $fresh->is_recalled);
        $this->assertSame(
            '這段原文應在 DB 保留供 super_admin 在 retention 期內稽核',
            $fresh->content,
            'recallMessage 不可清空 content（這是 Option C 分級權限與 retention 設計的前提）'
        );
    }
}
