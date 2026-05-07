<?php

namespace Tests\Unit\Support;

use App\Support\Mask;
use PHPUnit\Framework\TestCase;

class MaskTest extends TestCase
{
    public function test_email_masks_local_part_keeps_domain(): void
    {
        $this->assertSame('c***k@example.com', Mask::email('chuck@example.com'));
    }

    public function test_email_short_local_uses_full_mask(): void
    {
        $this->assertSame('***@example.com', Mask::email('ab@example.com'));
        $this->assertSame('***@example.com', Mask::email('a@example.com'));
    }

    public function test_email_null_or_invalid_returns_null(): void
    {
        $this->assertNull(Mask::email(null));
        $this->assertNull(Mask::email(''));
        $this->assertNull(Mask::email('no-at-sign'));
    }

    public function test_phone_handles_taiwan_local(): void
    {
        $this->assertSame('09xx-xxx-678', Mask::phone('0912345678'));
    }

    public function test_phone_handles_e164(): void
    {
        $this->assertSame('09xx-xxx-678', Mask::phone('+886912345678'));
    }

    public function test_phone_null_returns_null(): void
    {
        $this->assertNull(Mask::phone(null));
        $this->assertNull(Mask::phone(''));
    }

    public function test_phone_short_input_returns_raw(): void
    {
        $this->assertSame('123', Mask::phone('123'));
    }

    public function test_phone_long_non_10_digit_uses_middle_mask(): void
    {
        // length 13, first 3 = '123', last 3 = '123' (positions 10,11,12)
        $this->assertSame('123****123', Mask::phone('1234567890123'));
    }
}
