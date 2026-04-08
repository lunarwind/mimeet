<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatTest extends TestCase
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

    public function test_user_can_get_conversation_list(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $conv = Conversation::create([
            'uuid' => fake()->uuid(),
            'user_a_id' => min($userA->id, $userB->id),
            'user_b_id' => max($userA->id, $userB->id),
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($userA)->getJson('/api/v1/chats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['chats']]);
    }

    public function test_create_conversation_returns_existing_if_already_exists(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        // Create first
        $response1 = $this->actingAs($userA)->postJson('/api/v1/chats', [
            'user_id' => $userB->id,
        ]);
        $response1->assertStatus(201);
        $id1 = $response1->json('data.conversation.id');

        // Create again — should return same conversation
        $response2 = $this->actingAs($userA)->postJson('/api/v1/chats', [
            'user_id' => $userB->id,
        ]);
        $response2->assertStatus(201);
        $id2 = $response2->json('data.conversation.id');

        $this->assertEquals($id1, $id2);
    }

    public function test_lv1_blocked_after_30_messages(): void
    {
        // membership_level=2 passes middleware, but internal daily limit applies to non-Lv3
        $userA = $this->createUser(['membership_level' => 2]);
        $userB = $this->createUser();

        $conv = Conversation::create([
            'uuid' => fake()->uuid(),
            'user_a_id' => min($userA->id, $userB->id),
            'user_b_id' => max($userA->id, $userB->id),
            'last_message_at' => now(),
        ]);

        // Simulate 30 messages already sent today
        $cacheKey = "msg_daily:{$userA->id}:" . now()->format('Y-m-d');
        Cache::put($cacheKey, 30, now()->endOfDay());

        $response = $this->actingAs($userA)->postJson("/api/v1/chats/{$conv->id}/messages", [
            'content' => 'This should fail',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'MSG_LIMIT_EXCEEDED');
    }

    public function test_lv3_no_daily_limit(): void
    {
        $userA = $this->createUser(['membership_level' => 3]);
        $userB = $this->createUser();

        $conv = Conversation::create([
            'uuid' => fake()->uuid(),
            'user_a_id' => min($userA->id, $userB->id),
            'user_b_id' => max($userA->id, $userB->id),
            'last_message_at' => now(),
        ]);

        // Even with 100 messages today, Lv3 should still be able to send
        $cacheKey = "msg_daily:{$userA->id}:" . now()->format('Y-m-d');
        Cache::put($cacheKey, 100, now()->endOfDay());

        $response = $this->actingAs($userA)->postJson("/api/v1/chats/{$conv->id}/messages", [
            'content' => 'Lv3 unlimited',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/chats');
        $response->assertStatus(401);
    }

    public function test_non_participant_gets_403(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        $userC = $this->createUser();

        $conv = Conversation::create([
            'uuid' => fake()->uuid(),
            'user_a_id' => min($userA->id, $userB->id),
            'user_b_id' => max($userA->id, $userB->id),
            'last_message_at' => now(),
        ]);

        // userC is not a participant
        $response = $this->actingAs($userC)->getJson("/api/v1/chats/{$conv->id}/messages");
        $response->assertStatus(403);
    }

    public function test_mark_read_resets_unread_count(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $minId = min($userA->id, $userB->id);
        $maxId = max($userA->id, $userB->id);

        $conv = Conversation::create([
            'uuid' => fake()->uuid(),
            'user_a_id' => $minId,
            'user_b_id' => $maxId,
            'last_message_at' => now(),
            'unread_count_a' => $minId === $userA->id ? 5 : 0,
            'unread_count_b' => $maxId === $userA->id ? 5 : 0,
        ]);

        // Create some unread messages from userB to userA
        Message::create([
            'uuid' => fake()->uuid(),
            'conversation_id' => $conv->id,
            'sender_id' => $userB->id,
            'content' => 'Hello',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($userA)->patchJson("/api/v1/chats/{$conv->id}/read");
        $response->assertOk();

        $conv->refresh();
        $this->assertEquals(0, $conv->getUnreadCount($userA->id));
    }
}
