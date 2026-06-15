<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Stats\Range;
use PHPUnit\Framework\TestCase;

class RangeTest extends TestCase
{
    public function testKnownRangesSubtractFromNow(): void
    {
        $now = 1_700_000_000;
        self::assertSame($now - 7 * 86400, Range::fromTimestamp('7d', $now));
        self::assertSame($now - 30 * 86400, Range::fromTimestamp('30d', $now));
        self::assertSame($now - 90 * 86400, Range::fromTimestamp('90d', $now));
        self::assertSame($now - 180 * 86400, Range::fromTimestamp('180d', $now));
        self::assertSame($now - 365 * 86400, Range::fromTimestamp('365d', $now));
        // 'year' is kept as an alias of 365d for the Overview UI.
        self::assertSame($now - 365 * 86400, Range::fromTimestamp('year', $now));
    }

    public function testAllAndUnknownYieldNull(): void
    {
        self::assertNull(Range::fromTimestamp('all', 1_700_000_000));
        self::assertNull(Range::fromTimestamp('bogus', 1_700_000_000));
    }

    public function testNormalize(): void
    {
        self::assertSame('7d', Range::normalize('7d'));
        self::assertSame('180d', Range::normalize('180d'));
        self::assertSame('365d', Range::normalize('365d'));
        self::assertSame('all', Range::normalize('all'));
        self::assertSame('all', Range::normalize('nonsense'));
    }
}
