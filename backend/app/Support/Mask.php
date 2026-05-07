<?php

namespace App\Support;

final class Mask
{
    public static function email(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', $email, 2);
        if (mb_strlen($local) <= 2) {
            return "***@{$domain}";
        }
        $first = mb_substr($local, 0, 1);
        $last = mb_substr($local, -1);
        return "{$first}***{$last}@{$domain}";
    }

    public static function phone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }
        $local = str_starts_with($phone, '+886') ? '0' . substr($phone, 4) : $phone;
        if (strlen($local) === 10) {
            return substr($local, 0, 2) . 'xx-xxx-' . substr($local, -3);
        }
        if (strlen($local) >= 6) {
            return substr($local, 0, 3) . '****' . substr($local, -3);
        }
        return $local;
    }
}
