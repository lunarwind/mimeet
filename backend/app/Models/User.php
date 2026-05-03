<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->id === 1) {
                throw new \RuntimeException('Cannot delete system user (id=1). This account is protected.');
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    /**
     * User-editable fields only.
     * Admin-only fields (credit_score, status, membership_level) are managed
     * via CreditScoreService::adjust() and explicit $user->update() in admin controllers.
     */
    protected $fillable = [
        'email',
        'password',
        'nickname',
        'gender',
        'birth_date',
        'avatar_url',
        'avatar_slots',
        'bio',
        'height',
        'weight',
        'location',
        'occupation',
        'education',
        'interests',
        'email_verified',
        'phone',
        'phone_verified',
        'privacy_settings',
        'preferences',
        'profile',
        'last_active_at',
        'dnd_enabled',
        'dnd_start',
        'dnd_end',
        // F27 profile fields
        'style',
        'dating_budget',
        'dating_frequency',
        'dating_type',
        'relationship_goal',
        'smoking',
        'drinking',
        'car_owner',
        'availability',
        // F40 points
        'points_balance',
        'stealth_until',
    ];

    // Admin-only fields (credit_score, status, membership_level, suspended_at,
    // delete_requested_at, deleted_at) are NOT in $fillable.
    // Admin controllers use $user->forceFill([...])->save() for these fields.

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'date',
        'email_verified' => 'boolean',
        'phone_verified' => 'boolean',
        'membership_level' => 'decimal:1',
        'credit_score' => 'integer',
        'height' => 'integer',
        'interests' => 'array',
        'avatar_slots' => 'array',
        // privacy_settings handled by custom accessor (getPrivacySettingsAttribute)
        'credit_card_verified_at' => 'datetime',
        'last_active_at' => 'datetime',
        'suspended_at' => 'datetime',
        'delete_requested_at' => 'datetime',
        'deleted_at' => 'datetime',
        'password' => 'hashed',
        'phone' => 'encrypted',
        'dnd_enabled' => 'boolean',
        'dating_type' => 'array',
        'availability' => 'array',
        'car_owner' => 'boolean',
        'points_balance' => 'integer',
        'stealth_until' => 'datetime',
    ];

    public function getPrivacySettingsAttribute($value): array
    {
        $defaults = [
            'show_online_status' => true,
            'allow_profile_visits' => true,
            'show_in_search' => true,
            'show_last_active' => true,
            'allow_stranger_message' => true,
        ];
        return array_merge($defaults, $value ? (is_string($value) ? json_decode($value, true) : $value) : []);
    }

    /**
     * F22 Part B：是否處於免打擾時段
     * - 未啟用 / 時間未設定 → false
     * - start > end（如 22:00→08:00）視為跨午夜
     */
    /**
     * F42 隱身模式是否生效中（stealth_until 晚於現在）
     */
    public function isStealthActive(): bool
    {
        return $this->stealth_until && $this->stealth_until->isFuture();
    }

    public function isInDndPeriod(): bool
    {
        if (!$this->dnd_enabled) return false;
        if (!$this->dnd_start || !$this->dnd_end) return false;

        $now = now()->format('H:i');
        $start = substr((string) $this->dnd_start, 0, 5); // H:i
        $end = substr((string) $this->dnd_end, 0, 5);

        if ($start > $end) {
            return $now >= $start || $now < $end;
        }
        return $now >= $start && $now < $end;
    }

    /**
     * 推導用戶「不靠訂閱」應有的 membership_level（base level）。
     *
     * 訂閱到期降級時用此值取代 Lv3，避免直接寫死成 Lv1 而誤刪驗證升級的成果。
     *
     * 業務語意（PRD §3.2 v3 + Cleanup PR-C 拍板）：
     * - Lv2 = 「信用卡付款方式可用」（不限定為「驗證身份」）
     *   條件 A：credit_card_verified_at（NT$100 驗證 / admin verify_advanced）
     *   條件 B：曾有任一 ECPay 付款成功（type=subscription/points/verification 一視同仁，
     *           以 payments.paid_at 不為 null 判定，涵蓋 paid + refunded + refund_failed
     *           三種「曾付款」狀態）
     *   兩條件任一成立即可
     * - Lv1.5: 女性 + 照片驗證 approved
     * - Lv1  : phone_verified（email_verified 為註冊前置流程必然滿足）
     * - Lv0  : 剛註冊
     *
     * 設計影響：
     * - admin unverify_advanced 清除 cc_verified_at 後，若 user 已有 paid payment，
     *   base level 仍會推導為 Lv2。admin 想真正限制 user 應走停權流程。
     *   此 trade-off 已在 admin UI confirm modal 與 docs/decisions 中註記。
     */
    public function getBaseMembershipLevel(): float
    {
        if ($this->gender === 'male') {
            // 條件 A：信用卡驗證 / admin verify_advanced
            if ($this->credit_card_verified_at !== null) {
                return 2.0;
            }
            // 條件 B：曾有任一 ECPay 付款成功（信用卡可用證明）
            $hasSuccessfulPayment = Payment::where('user_id', $this->id)
                ->whereNotNull('paid_at')
                ->exists();
            if ($hasSuccessfulPayment) {
                return 2.0;
            }
        }

        if ($this->gender === 'female') {
            $hasApprovedPhotoVerification = UserVerification::where('user_id', $this->id)
                ->where('status', 'approved')
                ->exists();
            if ($hasApprovedPhotoVerification) {
                return 1.5;
            }
        }

        if ($this->phone_verified) {
            return 1.0;
        }

        return 0.0;
    }
}
