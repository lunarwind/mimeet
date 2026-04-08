<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
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

    public function test_user_can_get_notifications(): void
    {
        $user = $this->createUser();

        Notification::create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => '系統通知',
            'body' => '測試通知',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.notifications');
    }

    public function test_unread_count_in_response(): void
    {
        $user = $this->createUser();

        Notification::create(['user_id' => $user->id, 'type' => 'system', 'title' => 'A', 'is_read' => 0, 'created_at' => now()]);
        Notification::create(['user_id' => $user->id, 'type' => 'system', 'title' => 'B', 'is_read' => 0, 'created_at' => now()]);
        Notification::create(['user_id' => $user->id, 'type' => 'system', 'title' => 'C', 'is_read' => 1, 'created_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_mark_single_read(): void
    {
        $user = $this->createUser();

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => '未讀',
            'is_read' => 0,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->patchJson("/api/v1/notifications/{$notification->id}/read");
        $response->assertOk();

        $notification->refresh();
        $this->assertTrue($notification->is_read);
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_all_read_zeroes_count(): void
    {
        $user = $this->createUser();

        Notification::create(['user_id' => $user->id, 'type' => 'system', 'title' => 'A', 'is_read' => 0, 'created_at' => now()]);
        Notification::create(['user_id' => $user->id, 'type' => 'system', 'title' => 'B', 'is_read' => 0, 'created_at' => now()]);

        $response = $this->actingAs($user)->patchJson('/api/v1/notifications/read-all');
        $response->assertOk()
            ->assertJsonPath('data.updated_count', 2);

        $unread = Notification::where('user_id', $user->id)->where('is_read', 0)->count();
        $this->assertEquals(0, $unread);
    }

    public function test_notification_created_on_message_sent(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $conv = Conversation::create([
            'uuid' => fake()->uuid(),
            'user_a_id' => min($userA->id, $userB->id),
            'user_b_id' => max($userA->id, $userB->id),
            'last_message_at' => now(),
        ]);

        $this->actingAs($userA)->postJson("/api/v1/chats/{$conv->id}/messages", [
            'content' => 'Hello!',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $userB->id,
            'type' => 'new_message',
        ]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/notifications');
        $response->assertStatus(401);
    }
}
