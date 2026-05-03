<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cleanup PR-5: phone 重複註冊漏洞修復測試。
 *
 * 既有 users.phone 是 encrypted（IV 隨機），WHERE phone = ? 永遠 false。
 * 修法：phone_hash = SHA-256(E.164 normalize(phone))，註冊時用 phone_hash 比對。
 */
class RegisterPhoneUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(string $phone, string $email = 'new@example.com', string $nickname = '新用戶'): array
    {
        return [
            'data' => [
                'email'                 => $email,
                'password'              => 'Password123',
                'password_confirmation' => 'Password123',
                'nickname'              => $nickname,
                'gender'                => 'female',
                'birth_date'            => '1995-01-01',
                'phone'                 => $phone,
                'group'                 => 2,
                'terms_accepted'        => true,
                'privacy_accepted'      => true,
                'anti_fraud_read'       => true,
            ],
        ];
    }

    // ─── Case 1: user A 用 0912345678 註冊成功 ───
    public function test_first_registration_with_phone_succeeds(): void
    {
        $resp = $this->postJson('/api/v1/auth/register', $this->registerPayload('0912345678'));
        $resp->assertStatus(201);

        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals(hash('sha256', '+886912345678'), $user->phone_hash);
    }

    // ─── Case 2: user B 用同 phone 註冊 → 422 ───
    public function test_second_registration_with_same_phone_returns_422(): void
    {
        User::factory()->create(['phone' => '0912345678', 'email' => 'a@example.com', 'nickname' => 'A']);

        $resp = $this->postJson('/api/v1/auth/register',
            $this->registerPayload('0912345678', 'b@example.com', 'B'));
        $resp->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'phone');
    }

    // ─── Case 3: user B 用含 dash 同 phone → 422 (normalize 等價) ───
    public function test_phone_with_dash_is_treated_as_same(): void
    {
        User::factory()->create(['phone' => '0912345678', 'email' => 'a@example.com', 'nickname' => 'A']);

        $resp = $this->postJson('/api/v1/auth/register',
            $this->registerPayload('0912-345-678', 'b@example.com', 'B'));
        $resp->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'phone');
    }

    // ─── Case 4: user B 用含空格同 phone → 422 ───
    public function test_phone_with_whitespace_is_treated_as_same(): void
    {
        User::factory()->create(['phone' => '0912345678', 'email' => 'a@example.com', 'nickname' => 'A']);

        // register 端的 phone validate 是 regex `^09\d{8}$`，不允許空白；
        // 因此「用 normalize 等價」要從 +886 格式測（這也是 OTP 驗證後的真實格式）
        $resp = $this->postJson('/api/v1/auth/register',
            $this->registerPayload('+886912345678', 'b@example.com', 'B'));
        $resp->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'phone');
    }

    // ─── Case 5: user B 用不同 phone → 201 ───
    public function test_different_phone_succeeds(): void
    {
        User::factory()->create(['phone' => '0912345678', 'email' => 'a@example.com', 'nickname' => 'A']);

        $resp = $this->postJson('/api/v1/auth/register',
            $this->registerPayload('0987654321', 'b@example.com', 'B'));
        $resp->assertStatus(201);
    }

    // ─── Case 6: computePhoneHash 跨格式等價 ───
    public function test_compute_phone_hash_is_format_invariant(): void
    {
        $expected = hash('sha256', '+886912345678');
        $this->assertEquals($expected, User::computePhoneHash('0912345678'));
        $this->assertEquals($expected, User::computePhoneHash('+886912345678'));
        $this->assertEquals($expected, User::computePhoneHash('0912-345-678'));
        $this->assertEquals($expected, User::computePhoneHash(' 0912345678 '));
        $this->assertEquals($expected, User::computePhoneHash('912345678'));
    }

    // ─── Case 7: saving event 自動同步 phone_hash（建立時）───
    public function test_saving_event_auto_syncs_phone_hash_on_create(): void
    {
        $user = User::factory()->create(['phone' => '0912345678']);
        $this->assertEquals(hash('sha256', '+886912345678'), $user->fresh()->phone_hash);
    }

    // ─── Case 8: user 改 phone 時 phone_hash 同步更新 ───
    public function test_saving_event_updates_phone_hash_when_phone_changes(): void
    {
        $user = User::factory()->create(['phone' => '0912345678']);
        $this->assertEquals(hash('sha256', '+886912345678'), $user->fresh()->phone_hash);

        $user->phone = '0987654321';
        $user->save();
        $this->assertEquals(hash('sha256', '+886987654321'), $user->fresh()->phone_hash);
    }

    // ─── Case 9: normalizePhone 完整單元測試 ───
    public function test_normalize_phone_unit(): void
    {
        $this->assertEquals('+886912345678', User::normalizePhone('0912345678'));
        $this->assertEquals('+886912345678', User::normalizePhone('+886912345678'));
        $this->assertEquals('+886912345678', User::normalizePhone('0912-345-678'));
        $this->assertEquals('+886912345678', User::normalizePhone(' 0912345678 '));
        $this->assertEquals('+886912345678', User::normalizePhone('912345678'));
        $this->assertNull(User::normalizePhone(null));
        $this->assertNull(User::normalizePhone(''));
    }

    // ─── Case 10: phone「變形」場景（PR-5 核心保證）───
    // 驗：註冊時 "0912345678" 與 OTP 驗證後 "+886912345678" 算出的 phone_hash 必須相同
    public function test_register_then_otp_verify_keeps_phone_hash_stable(): void
    {
        // 第一步：user A 註冊 "0912345678"
        $userA = User::factory()->create(['phone' => '0912345678']);
        $hashAtRegister = $userA->fresh()->phone_hash;

        // 第二步：模擬 OTP 驗證後 phone 改寫成 E.164
        $userA->phone = '+886912345678';
        $userA->save();
        $hashAfterOtp = $userA->fresh()->phone_hash;

        $this->assertEquals($hashAtRegister, $hashAfterOtp,
            'phone normalization 必須讓 "0912345678" 與 "+886912345678" 算出同一 hash，' .
            '否則同一 user 在 OTP 驗證前後會誤觸 unique 衝突');

        // 第三步：user B 用「另一格式」相同號碼註冊應被擋
        $resp = $this->postJson('/api/v1/auth/register',
            $this->registerPayload('0912-345-678', 'b@example.com', 'B'));
        $resp->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'phone');
    }
}
