<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PR-4 反轉決策守護測試:phone-change initiate response 必須使用 `new_phone` 欄位
 * 並回 raw E.164,不可再使用 `new_phone_masked` 欄位。
 */
class PhoneChangeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(SmsService::class, function ($mock) {
            $mock->shouldReceive('sendOtp')->andReturnTrue();
        });
    }

    public function test_initiate_response_returns_raw_new_phone(): void
    {
        $user = User::factory()->create([
            'phone' => '0912345678',
            'phone_verified' => true,
            'status' => 'active',
            'membership_level' => 1,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/phone-change/initiate', [
            'new_phone' => '0987654321',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.new_phone', '+886987654321')
            ->assertJsonMissingPath('data.new_phone_masked');
    }
}
