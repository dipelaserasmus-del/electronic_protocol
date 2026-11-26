<?php

namespace Tests\Unit;

use App\Support\Duration;
use PHPUnit\Framework\TestCase;

/** R1o (owner, 2026-11-26): the time left to change a protocol, readable. */
class DurationTest extends TestCase
{
    public function test_days_hours_and_minutes(): void
    {
        $this->assertSame('4 μέρες, 2 ώρες, 56 λεπτά', Duration::text(5936 * 60));   // the owner's «5936 λεπτά»
        $this->assertSame('2 μέρες, 4 ώρες, 3 λεπτά', Duration::text((2 * 1440 + 4 * 60 + 3) * 60));
        $this->assertSame('1 μέρα, 1 ώρα, 1 λεπτό', Duration::text(86400 + 3600 + 60));
        $this->assertSame('1 μέρα', Duration::text(86400));
        $this->assertSame('2 ώρες, 5 λεπτά', Duration::text(2 * 3600 + 5 * 60));
        $this->assertSame('1 λεπτό', Duration::text(1), 'rounded up: never less than is left');
        $this->assertSame('3 λεπτά', Duration::text(121));
        $this->assertSame('0 λεπτά', Duration::text(0));
    }
}
