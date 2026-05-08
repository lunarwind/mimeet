<?php

namespace Tests\Unit\Services;

use App\Exceptions\PhoneConflictException;
use App\Models\PhoneChangeHistory;
use App\Models\RegistrationBlacklist;
use App\Models\User;
use App\Services\BlacklistService;
use App\Services\PhoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneServiceTest extends TestCase
{
    use RefreshDatabase;

    private PhoneService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PhoneService::class);
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

    public function test_set_verified_phone_throws_on_unique_conflict(): void
    {
        $existing = $this->verifiedPhoneUser(['phone' => '0987654321']);
        $newUser = User::factory()->create([
            'phone' => '0912345678',
            'phone_verified' => false,
            'membership_level' => 0,
        ]);

        $this->expectException(PhoneConflictException::class);
        $this->expectExceptionMessage('此手機號碼已被使用');

        $this->service->setVerifiedPhone($newUser, '0987654321', 'verify');
    }

    public function test_set_verified_phone_throws_on_blacklist_hit(): void
    {
        $hash = User::computePhoneHash('0987654321');
        RegistrationBlacklist::create([
            'type' => 'mobile',
            'value_hash' => $hash,
            'value_masked' => '09xx-xxx-321',
            'source' => 'manual',
            'created_by' => 1,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'phone' => '0987654321',
            'phone_verified' => false,
        ]);

        $this->expectException(PhoneConflictException::class);
        $this->expectExceptionMessage('此手機號碼已被使用');

        $this->service->setVerifiedPhone($user, '0987654321', 'verify');
    }

    public function test_blacklist_check_blocks_same_phone_already_verified_noop_user(): void
    {
        // v4 S1: 已 verified user 同 phone 重複 verify 也必須跑 blacklist check
        $user = $this->verifiedPhoneUser();
        $hash = $user->phone_hash;

        RegistrationBlacklist::create([
            'type' => 'mobile',
            'value_hash' => $hash,
            'value_masked' => '09xx-xxx-678',
            'source' => 'manual',
            'created_by' => 1,
            'is_active' => true,
        ]);

        $this->expectException(PhoneConflictException::class);
        $this->service->setVerifiedPhone($user, $user->phone, 'verify');
    }

    public function test_blacklist_check_blocks_same_phone_unverified_user(): void
    {
        // v3 B1: register 後被加 blacklist、尚未 verify 的 user 不能 verify
        $hash = User::computePhoneHash('0912345678');
        RegistrationBlacklist::create([
            'type' => 'mobile',
            'value_hash' => $hash,
            'value_masked' => '09xx-xxx-678',
            'source' => 'manual',
            'created_by' => 1,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'phone' => '0912345678',
            'phone_verified' => false,
        ]);

        $this->expectException(PhoneConflictException::class);
        $this->service->setVerifiedPhone($user, $user->phone, 'verify');
    }

    public function test_set_verified_phone_writes_history_record(): void
    {
        $user = User::factory()->create([
            'phone' => '0912345678',
            'phone_verified' => false,
            'membership_level' => 0,
        ]);

        $result = $this->service->setVerifiedPhone($user, $user->phone, 'verify');

        $this->assertDatabaseHas('phone_change_histories', [
            'user_id' => $user->id,
            'new_phone_hash' => $result->newPhoneHash,
            'source' => 'verify',
        ]);
    }

    public function test_same_phone_still_marks_unverified_user_as_verified(): void
    {
        // v2 B2: no-op 邊界 — phone 沒變但 verified=false 必須仍 set verified
        $user = User::factory()->create([
            'phone' => '0912345678',
            'phone_verified' => false,
            'membership_level' => 0,
        ]);

        $result = $this->service->setVerifiedPhone($user, $user->phone, 'verify');

        $this->assertTrue($result->verifiedChanged);
        $user->refresh();
        $this->assertTrue((bool) $user->phone_verified);
        $this->assertGreaterThanOrEqual(1, $user->membership_level);
    }

    public function test_returns_membership_changed_when_lv0_user_promoted(): void
    {
        // v3 Y1: membershipChanged 邊界
        $user = User::factory()->create([
            'phone' => '0912345678',
            'phone_verified' => true,  // 罕見:已 verified 但 lv0
            'membership_level' => 0,
        ]);

        $result = $this->service->setVerifiedPhone($user, $user->phone, 'verify');

        $this->assertTrue($result->membershipChanged);
    }

    public function test_returns_result_object_with_verified_changed_flag(): void
    {
        // v2 O3
        $user = $this->verifiedPhoneUser();
        $result = $this->service->setVerifiedPhone($user, $user->phone, 'verify');

        $this->assertFalse($result->verifiedChanged);
        $this->assertFalse($result->changed);
        $this->assertSame($user->phone_hash, $result->newPhoneHash);
    }

    public function test_throws_phone_conflict_for_invalid_phone_format_returning_null_normalize(): void
    {
        // v8 R5: defensive — normalizePhone() return null 時不能 type error
        $user = User::factory()->create([
            'phone' => '0912345678',
            'phone_verified' => false,
        ]);

        $this->expectException(PhoneConflictException::class);
        $this->service->setVerifiedPhone($user, '', 'verify');
    }
}
