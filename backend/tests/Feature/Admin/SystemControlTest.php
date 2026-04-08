<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SystemControlTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'membership_level' => 3,
            'credit_score' => 100,
            'status' => 'active',
            'password' => Hash::make('admin_password'),
        ]);
    }

    private function seedSettings(): void
    {
        $settings = [
            ['key_name' => 'app.mode', 'value' => 'testing'],
            ['key_name' => 'app.maintenance', 'value' => '0'],
            ['key_name' => 'app.version', 'value' => '1.0.0'],
            ['key_name' => 'sms.provider', 'value' => 'disabled'],
            ['key_name' => 'mail.host', 'value' => 'mailpit'],
            ['key_name' => 'mail.port', 'value' => '1025'],
            ['key_name' => 'mail.from_address', 'value' => 'noreply@mimeet.tw'],
            ['key_name' => 'mail.from_name', 'value' => 'MiMeet'],
        ];
        foreach ($settings as $s) {
            SystemSetting::updateOrCreate(
                ['key_name' => $s['key_name']],
                array_merge($s, ['value_type' => 'string'])
            );
        }
    }

    public function test_super_admin_can_get_system_control_settings(): void
    {
        $admin = $this->createAdmin();
        $this->seedSettings();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/settings/system-control');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['app_mode', 'mail', 'sms', 'database']]);
    }

    public function test_switch_mode_requires_correct_password(): void
    {
        $admin = $this->createAdmin();
        $this->seedSettings();

        $response = $this->actingAs($admin)->patchJson('/api/v1/admin/settings/app-mode', [
            'mode' => 'production',
            'confirm_password' => 'admin_password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.mode', 'production');

        $this->assertEquals('production', SystemSetting::get('app.mode'));
    }

    public function test_switch_mode_with_wrong_password_returns_422(): void
    {
        $admin = $this->createAdmin();
        $this->seedSettings();

        $response = $this->actingAs($admin)->patchJson('/api/v1/admin/settings/app-mode', [
            'mode' => 'production',
            'confirm_password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'PASSWORD_INCORRECT');
    }

    public function test_update_mail_settings_saves_to_db(): void
    {
        $admin = $this->createAdmin();
        $this->seedSettings();

        $response = $this->actingAs($admin)->patchJson('/api/v1/admin/settings/mail', [
            'host' => 'smtp.sendgrid.net',
            'port' => 587,
            'from_address' => 'hello@mimeet.tw',
        ]);

        $response->assertOk();
        $this->assertEquals('smtp.sendgrid.net', SystemSetting::get('mail.host'));
        $this->assertEquals('587', SystemSetting::get('mail.port'));
    }

    public function test_mail_password_not_returned_in_response(): void
    {
        $admin = $this->createAdmin();
        $this->seedSettings();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/settings/system-control');

        $response->assertOk();
        $this->assertEquals('****', $response->json('data.mail.password'));
        $this->assertEquals('****', $response->json('data.sms.mitake.password'));
        $this->assertEquals('****', $response->json('data.database.password'));
    }

    public function test_update_sms_provider_to_mitake(): void
    {
        $admin = $this->createAdmin();
        $this->seedSettings();

        $response = $this->actingAs($admin)->patchJson('/api/v1/admin/settings/sms', [
            'provider' => 'mitake',
            'mitake' => ['username' => 'test_account'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'mitake');

        $this->assertEquals('mitake', SystemSetting::get('sms.provider'));
    }

    public function test_update_sms_provider_to_disabled(): void
    {
        $admin = $this->createAdmin();
        $this->seedSettings();

        $response = $this->actingAs($admin)->patchJson('/api/v1/admin/settings/sms', [
            'provider' => 'disabled',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'disabled');
    }

    public function test_test_mail_returns_success(): void
    {
        $admin = $this->createAdmin();
        Mail::fake();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/settings/mail/test', [
            'test_email' => 'test@example.com',
        ]);

        $response->assertOk();
        Mail::assertSent(\App\Mail\TestMail::class);
    }

    public function test_get_app_mode_returns_status(): void
    {
        $admin = $this->createAdmin();
        $this->seedSettings();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/settings/system/app-mode');

        $response->assertOk()
            ->assertJsonPath('data.mode', 'testing')
            ->assertJsonPath('data.ecpay_sandbox', true);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/admin/settings/system-control');
        $response->assertStatus(401);
    }
}
