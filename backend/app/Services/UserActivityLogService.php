<?php

namespace App\Services;

use App\Models\UserActivityLog;
use Illuminate\Http\Request;

class UserActivityLogService
{
    /**
     * Log a user activity with request context.
     */
    public static function log(int $userId, string $action, ?array $metadata = null, ?Request $request = null): UserActivityLog
    {
        return UserActivityLog::log(
            $userId,
            $action,
            $metadata,
            $request?->ip(),
            $request?->userAgent(),
        );
    }

    /**
     * Log profile update — records which fields changed.
     */
    public static function logProfileUpdate(int $userId, array $changedFields, ?Request $request = null): UserActivityLog
    {
        return self::log($userId, 'profile_update', [
            'changed_fields' => $changedFields,
        ], $request);
    }

    /**
     * Log avatar/photo change.
     */
    public static function logPhotoChange(int $userId, string $type, ?Request $request = null): UserActivityLog
    {
        return self::log($userId, 'photo_' . $type, [
            'type' => $type, // upload, delete, set_avatar
        ], $request);
    }

    /**
     * Log phone number change.
     */
    public static function logPhoneChange(int $userId, ?Request $request = null): UserActivityLog
    {
        return self::log($userId, 'phone_changed', null, $request);
    }

    /**
     * Log login event.
     */
    public static function logLogin(int $userId, ?Request $request = null): UserActivityLog
    {
        return self::log($userId, 'login', null, $request);
    }

    /**
     * Log verification submission.
     */
    public static function logVerification(int $userId, string $verifyType, ?Request $request = null): UserActivityLog
    {
        return self::log($userId, 'verification_submitted', [
            'verify_type' => $verifyType,
        ], $request);
    }
}
