<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
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

        $conv->update(['last_message_id' => $msg->id ?? null]);

        return $conv;
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

    public function test_conversations_shows_recalled_message_placeholder(): void
    {
        $admin = $this->createAdmin();
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

        Message::create([
            'uuid' => Str::uuid()->toString(),
            'conversation_id' => $conv->id,
            'sender_id' => $userA->id,
            'content' => '這是秘密訊息',
            'type' => 'text',
            'is_recalled' => true,
            'recalled_at' => now(),
            'sent_at' => now(),
        ]);

        $response = $this->withAdminAuth($admin)->getJson(
            "/api/v1/admin/chat-logs/conversations?user_a={$userA->id}&user_b={$userB->id}"
        );

        $response->assertOk();
        $messages = $response->json('data.messages');
        $this->assertNull($messages[0]['content']);
        $this->assertTrue($messages[0]['is_recalled']);
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
