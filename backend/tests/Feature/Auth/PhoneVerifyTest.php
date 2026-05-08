<?php

namespace Tests\Feature\Auth;

use App\Models\RegistrationBlacklist;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PhoneVerifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(SmsService::class, function ($mock) {
            $mock->shouldReceive('sendOtp')->andReturnTrue();
        });
    }

    private function verifiedPhoneUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'phone' => '0912345678',
            'phone_verified' => true,
            'membership_level' => 1,
            'status' => 'active',
        ], $attrs));
    }

    public function test_verify_phone_send_ignores_request_phone_param(): void
    {
        $user = $this->verifiedPhoneUser(['phone' => '0912345678']);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/auth/verify-phone/send', [
            'phone' => '0900000000', // 攻擊嘗試:塞別的號碼
        ]);

        $res->assertOk();
        // OTP 應該存到 user.phone 對應的 e164,不是攻擊的號碼
        $userE164 = User::normalizePhone($user->phone);
        $this->assertNotNull(Cache::get("otp:phone:{$userE164}"));
        $this->assertNull(Cache::get('otp:phone:+886900000000'));
    }

    public function test_verify_phone_send_returns_422_when_user_phone_null(): void
    {
        $user = User::factory()->create(['phone' => null, 'status' => 'active']);
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/auth/verify-phone/send', []);

        $res->assertStatus(422)->assertJsonPath('error.code', 'PHONE_NOT_SET');
    }

    public function test_verify_phone_confirm_ignores_request_phone_param(): void
    {
        $user = User::factory()->create([
            'phone' => '0912345678',
            'phone_verified' => false,
            'membership_level' => 0,
            'status' => 'active',
        ]);
        Sanctum::actingAs($user);

        // 預先放 OTP 到 user.phone 對應的 e164
        $userE164 = User::normalizePhone($user->phone);
        Cache::put("otp:phone:{$userE164}", '123456', 300);

        $res = $this->postJson('/api/v1/auth/verify-phone/confirm', [
            'phone' => '0900000000', // 攻擊嘗試
            'code' => '123456',
        ]);

        $res->assertOk();
        $user->refresh();
        // user.phone 不該被改成攻擊的號碼
        $this->assertSame('+886912345678', $user->phone);
        $this->assertTrue((bool) $user->phone_verified);
    }

    public function test_verify_phone_confirm_blocks_blacklisted_user_phone(): void
    {
        $user = User::factory()->create([
            'phone' => '0912345678',
            'phone_verified' => false,
            'status' => 'active',
        ]);
        $hash = User::computePhoneHash($user->phone);
        RegistrationBlacklist::create([
            'type' => 'mobile',
            'value_hash' => $hash,
            'value_masked' => '09xx-xxx-678',
            'source' => 'manual',
            'created_by' => 1,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);
        $userE164 = User::normalizePhone($user->phone);
        Cache::put("otp:phone:{$userE164}", '123456', 300);

        $res = $this->postJson('/api/v1/auth/verify-phone/confirm', [
            'code' => '123456',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('errors.phone.0', '此手機號碼已被使用');

        $user->refresh();
        $this->assertFalse((bool) $user->phone_verified);
    }

    public function test_verify_phone_confirm_same_phone_marks_user_verified(): void
    {
        $user = User::factory()->create([
            'phone' => '0912345678',
            'phone_verified' => false,
            'membership_level' => 0,
            'status' => 'active',
        ]);
        Sanctum::actingAs($user);

        $userE164 = User::normalizePhone($user->phone);
        Cache::put("otp:phone:{$userE164}", '123456', 300);

        $res = $this->postJson('/api/v1/auth/verify-phone/confirm', [
            'code' => '123456',
        ]);

        $res->assertOk();
        $user->refresh();
        $this->assertTrue((bool) $user->phone_verified);
        $this->assertGreaterThanOrEqual(1, $user->membership_level);
    }
}
