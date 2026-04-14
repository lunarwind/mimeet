<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GdprService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterUniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'data' => [
                'email'                 => 'test@example.com',
                'password'              => 'Password123',
                'password_confirmation' => 'Password123',
                'nickname'              => '測試用戶',
                'gender'                => 'female',
                'birth_date'            => '1995-01-01',
                'phone'                 => '0912345678',
                'group'                 => 2,
                'terms_accepted'        => true,
                'privacy_accepted'      => true,
                'anti_fraud_read'       => true,
            ],
        ], $overrides);
    }

    /** 情境 1：活躍用戶的 email 不能重複 */
    public function test_cannot_register_with_existing_active_email(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'status' => 'active']);

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertStatus(422);
        $details = $response->json('error.details');
        $this->assertTrue(collect($details)->contains('field', 'email'), '應回傳 email 欄位錯誤');
    }

    /** 情境 2：已刪除用戶的 email 可以重新使用 */
    public function test_can_register_with_email_from_deleted_user(): void
    {
        $gdpr = app(GdprService::class);
        $deleted = User::factory()->create([
            'email'    => 'test@example.com',
            'nickname' => '舊用戶',
            'phone'    => '0912345678',
            'status'   => 'pending_deletion',
            'delete_requested_at' => now()->subDays(8),
            'password' => Hash::make('OldPass123'),
        ]);
        $gdpr->anonymizeUser($deleted);

        // 確認匿名化後 email 已被改掉
        $deleted->refresh();
        $this->assertNotEquals('test@example.com', $deleted->email);

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertStatus(201);
    }

    /** 情境 3：活躍用戶的 nickname 不能重複 */
    public function test_cannot_register_with_existing_active_nickname(): void
    {
        User::factory()->create(['nickname' => '測試用戶', 'status' => 'active']);

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertStatus(422);
        $details = $response->json('error.details');
        $this->assertTrue(collect($details)->contains('field', 'nickname'), '應回傳 nickname 欄位錯誤');
    }

    /** 情境 4：已刪除用戶的 nickname 可以重新使用 */
    public function test_can_register_with_nickname_from_deleted_user(): void
    {
        $gdpr = app(GdprService::class);
        $deleted = User::factory()->create([
            'email'    => 'old@example.com',
            'nickname' => '測試用戶',
            'status'   => 'pending_deletion',
            'delete_requested_at' => now()->subDays(8),
            'password' => Hash::make('OldPass123'),
        ]);
        $gdpr->anonymizeUser($deleted);

        // 已刪除用戶的 nickname 已被改為 '已刪除用戶'，'測試用戶' 已釋出
        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertStatus(201);
    }

    /** 情境 5：活躍用戶的手機號碼不能重複 */
    public function test_cannot_register_with_existing_active_phone(): void
    {
        User::factory()->create([
            'phone'  => '0912345678',
            'email'  => 'other@example.com',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertStatus(422);
        $details = $response->json('error.details');
        $this->assertTrue(collect($details)->contains('field', 'phone'), '應回傳 phone 欄位錯誤');
    }

    /** 情境 6：已刪除用戶的手機號碼（已被設為 NULL）可以重新使用 */
    public function test_can_register_with_phone_from_deleted_user(): void
    {
        $gdpr = app(GdprService::class);
        $deleted = User::factory()->create([
            'email'    => 'old2@example.com',
            'nickname' => '其他用戶',
            'phone'    => '0912345678',
            'status'   => 'pending_deletion',
            'delete_requested_at' => now()->subDays(8),
            'password' => Hash::make('OldPass123'),
        ]);
        $gdpr->anonymizeUser($deleted);

        // GdprService 已將 phone 設為 NULL
        $deleted->refresh();
        $this->assertNull($deleted->phone);

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertStatus(201);
    }
}
