<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PR-4 反轉決策守護測試:user-self response 的 phone 欄位必須回 raw,
 * 不可再含 Mask::phone() 的 'xx-xxx-' 樣式。
 *
 * 對應 pre-merge guard 14aw 與 docs/API-001 §Phone 欄位 mask 原則。
 */
class PhoneResponseRawTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_response_returns_raw_phone(): void
    {
        $user = User::factory()->create([
            'phone' => '0912345678',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $phone = $response->json('data.user.phone');
        $this->assertSame($user->phone, $phone);
        $this->assertStringNotContainsString('xx-xxx-', (string) $phone);
    }

    public function test_me_response_returns_raw_phone(): void
    {
        $user = User::factory()->create([
            'phone' => '0912345678',
            'status' => 'active',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();
        $phone = $response->json('data.user.phone');
        $this->assertSame($user->phone, $phone);
        $this->assertStringNotContainsString('xx-xxx-', (string) $phone);
    }

    public function test_register_response_returns_raw_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'newuser@test.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'nickname' => 'Newbie',
            'gender' => 'female',
            'birth_date' => '2000-01-01',
            'phone' => '0987654321',
            'terms_accepted' => true,
            'privacy_accepted' => true,
            'anti_fraud_read' => true,
        ]);

        $response->assertStatus(201);
        $phone = $response->json('data.user.phone');
        $this->assertSame('0987654321', $phone);
        $this->assertStringNotContainsString('xx-xxx-', (string) $phone);
    }
}
