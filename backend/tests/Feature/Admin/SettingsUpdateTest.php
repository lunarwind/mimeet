<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 守護 PATCH /admin/settings 的 payload 攤平 + data_retention_days super_admin 限制。
 *
 * 背景：前端兩個既有路徑（point settings、retention）都用 `{settings: {...}}` 包覆送出，
 * 但 controller 過往直接 iterate `$request->except(['_token'])`，會把 `'settings'` 當 key
 * 寫進 system_settings、value 變 "Array"（silent bug）。本次 Phase 2.3 補上攤平 + super_admin gate。
 * 本檔案以三條案例守護新行為與向下相容，避免回頭走回 broken 路徑。
 */
class SettingsUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(string $role = 'super_admin'): AdminUser
    {
        return AdminUser::factory()->create([
            'role' => $role,
            'password' => Hash::make('admin_password'),
        ]);
    }

    private function withAdminAuth(AdminUser $admin): self
    {
        $token = $admin->createToken('test')->plainTextToken;
        return $this->withHeaders(['Authorization' => "Bearer {$token}"]);
    }

    public function test_update_settings_supports_nested_settings_payload(): void
    {
        $admin = $this->createAdmin('super_admin');

        $response = $this->withAdminAuth($admin)->patchJson('/api/v1/admin/settings', [
            'settings' => [
                'credit_score_initial' => '70',
                'data_retention_days' => '200',
            ],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertSame('70', SystemSetting::get('credit_score_initial'));
        $this->assertSame('200', SystemSetting::get('data_retention_days'));

        // 退化守護：絕對不可再出現舊版 bug 寫入的 settings='Array' 痕跡
        $this->assertNull(
            SystemSetting::where('key_name', 'settings')->first(),
            '舊版 bug 會把整個 "settings" 物件當 key 寫入並 cast 成 "Array"；本案例必須阻止此退化'
        );
    }

    public function test_update_settings_still_supports_flat_payload_backward_compat(): void
    {
        $admin = $this->createAdmin('super_admin');

        // 直接送扁平 key（不包 settings：兼容既有 credit-score 等流程改回直送的可能）
        $response = $this->withAdminAuth($admin)->patchJson('/api/v1/admin/settings', [
            'credit_score_initial' => '80',
        ]);

        $response->assertOk();
        $this->assertSame('80', SystemSetting::get('credit_score_initial'));
    }

    public function test_update_data_retention_days_by_admin_returns_403(): void
    {
        $admin = $this->createAdmin('admin');  // 非 super_admin

        $response = $this->withAdminAuth($admin)->patchJson('/api/v1/admin/settings', [
            'settings' => ['data_retention_days' => '90'],
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_4003');

        // 退化守護：admin 即使被擋下，也不可有副作用寫入
        $this->assertNotSame('90', SystemSetting::get('data_retention_days'));
    }

    public function test_update_other_settings_by_admin_still_allowed(): void
    {
        $admin = $this->createAdmin('admin');

        // 不含 data_retention_days，admin 應可改其他既有設定（如點數）
        $response = $this->withAdminAuth($admin)->patchJson('/api/v1/admin/settings', [
            'settings' => ['credit_score_initial' => '65'],
        ]);

        $response->assertOk();
        $this->assertSame('65', SystemSetting::get('credit_score_initial'));
    }
}
