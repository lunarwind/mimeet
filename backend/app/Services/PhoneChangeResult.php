<?php

namespace App\Services;

final readonly class PhoneChangeResult
{
    public function __construct(
        public bool $changed,
        public bool $verifiedChanged,
        public bool $membershipChanged,
        public ?string $oldPhoneHash,
        public string $newPhoneHash,
    ) {}
}
