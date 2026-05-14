<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F07 — UserController::show() 內 profile.photos 應從 avatar_url + avatar_slots
 * 衍生映射，不再硬編碼空陣列。
 * 對齊 frontend/src/api/users.ts photos 型別：{ id, url, is_avatar, order }[]
 */
class UserProfilePhotosTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_returns_photos_from_avatar_slots_with_dedup(): void
    {
        $user = User::factory()->create([
            'avatar_url' => 'https://example.com/main.jpg',
            'avatar_slots' => [
                'https://example.com/main.jpg',
                'https://example.com/extra1.jpg',
                'https://example.com/extra2.jpg',
            ],
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson("/api/v1/users/{$user->id}");

        $response->assertOk();
        $photos = $response->json('data.user.photos');

        $this->assertCount(3, $photos, 'avatar_url 與 slots[0] 重複，去重後共 3 筆');
        $this->assertEquals('https://example.com/main.jpg', $photos[0]['url']);
        $this->assertTrue($photos[0]['is_avatar']);
        $this->assertEquals(0, $photos[0]['order']);
        $this->assertEquals(1, $photos[0]['id']);

        $this->assertFalse($photos[1]['is_avatar']);
        $this->assertEquals('https://example.com/extra1.jpg', $photos[1]['url']);
        $this->assertEquals(1, $photos[1]['order']);

        $this->assertFalse($photos[2]['is_avatar']);
        $this->assertEquals('https://example.com/extra2.jpg', $photos[2]['url']);
        $this->assertEquals(2, $photos[2]['order']);
    }

    public function test_profile_returns_empty_photos_when_no_avatar(): void
    {
        $user = User::factory()->create([
            'avatar_url' => null,
            'avatar_slots' => null,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson("/api/v1/users/{$user->id}");

        $response->assertOk();
        $this->assertEquals([], $response->json('data.user.photos'));
    }

    public function test_profile_returns_only_avatar_when_slots_empty(): void
    {
        $user = User::factory()->create([
            'avatar_url' => 'https://example.com/only.jpg',
            'avatar_slots' => [],
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson("/api/v1/users/{$user->id}");

        $response->assertOk();
        $photos = $response->json('data.user.photos');

        $this->assertCount(1, $photos);
        $this->assertEquals('https://example.com/only.jpg', $photos[0]['url']);
        $this->assertTrue($photos[0]['is_avatar']);
    }
}
