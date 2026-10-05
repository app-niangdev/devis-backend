<?php

namespace Tests\Unit;

use App\Support\SenegalPhone;
use PHPUnit\Framework\TestCase;

class SenegalPhoneTest extends TestCase
{
    public function test_normalize(): void
    {
        $this->assertSame('771234567', SenegalPhone::normalize('77 123 45 67'));
        $this->assertSame('701234567', SenegalPhone::normalize('+221 70.123.45.67'));
        $this->assertSame('781234567', SenegalPhone::normalize('00221781234567'));
        $this->assertSame('711234567', SenegalPhone::normalize('221711234567'));

        foreach (['331234567', '721234567', '7712345', '7712345678', '', null] as $invalid) {
            $this->assertNull(SenegalPhone::normalize($invalid));
        }
    }

    public function test_display_helpers(): void
    {
        $this->assertSame('77 123 45 67', SenegalPhone::format('771234567'));
        $this->assertSame('77 *** ** 67', SenegalPhone::mask('771234567'));
        $this->assertSame('221771234567@c.us', SenegalPhone::chatId('77 123 45 67'));
        $this->assertNull(SenegalPhone::chatId('331234567'));
    }
}
