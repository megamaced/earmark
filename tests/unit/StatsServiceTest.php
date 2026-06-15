<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Db\ListenMapper;
use OCA\Earmark\Db\LovedMapper;
use OCA\Earmark\Service\StatsService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

class StatsServiceTest extends TestCase
{
    private ?LovedMapper $lovedMapper = null;

    private function service(ListenMapper $mapper): StatsService
    {
        $time = $this->createStub(ITimeFactory::class);
        $time->method('getTime')->willReturn(1_700_000_000);
        $loved = $this->lovedMapper ?? $this->createStub(LovedMapper::class);
        return new StatsService($mapper, $loved, $time);
    }

    public function testClockBucketsByHourOfDayUtc(): void
    {
        // 1700000000 = 2023-11-14 22:13:20 UTC (hour 22)
        // +3600 → hour 23; +7200 → hour 23 (23:13); 0 → 00:00 (hour 0)
        $mapper = $this->createStub(ListenMapper::class);
        $mapper->method('listenedAtSince')->willReturn([
            1_700_000_000,            // hour 22
            1_700_000_000 + 3600,     // hour 23
            1_700_000_000 + 3600,     // hour 23
            86400,                    // 1970-01-02 00:00:00 → hour 0
        ]);

        $clock = $this->service($mapper)->clock('alice', 'all');

        self::assertCount(24, $clock);
        self::assertSame(1, $clock[22]);
        self::assertSame(2, $clock[23]);
        self::assertSame(1, $clock[0]);
        self::assertSame(0, $clock[12]);
    }

    public function testTopArtistDispatch(): void
    {
        $mapper = $this->createMock(ListenMapper::class);
        $mapper->expects($this->once())->method('topArtists')
            ->willReturn([['name' => 'The Beatles', 'count' => 42]]);
        $mapper->expects($this->never())->method('topTracks');

        $result = $this->service($mapper)->top('alice', 'artist', 'year', 20);

        self::assertSame('The Beatles', $result[0]['name']);
    }

    public function testTopUnknownTypeReturnsEmpty(): void
    {
        $mapper = $this->createStub(ListenMapper::class);
        self::assertSame([], $this->service($mapper)->top('alice', 'bogus', 'all'));
    }

    public function testPerYearBucketsByCalendarYearUtc(): void
    {
        $mapper = $this->createStub(ListenMapper::class);
        $mapper->method('listenedAtSince')->willReturn([
            1_700_000_000,        // 2023
            1_700_000_100,        // 2023
            1_600_000_000,        // 2020
            86400,                // 1970
        ]);

        $years = $this->service($mapper)->perYear('alice');

        self::assertSame([
            ['year' => 1970, 'count' => 1],
            ['year' => 2020, 'count' => 1],
            ['year' => 2023, 'count' => 2],
        ], $years);
    }

    public function testWindowPrefersExplicitBoundsOverRange(): void
    {
        $service = $this->service($this->createStub(ListenMapper::class));

        // Explicit bounds win.
        self::assertSame([100, 200], $service->window('all', 100, 200));
        // A single explicit bound still counts as custom (the other stays open).
        self::assertSame([100, null], $service->window('30d', 100, null));
        // No explicit bounds → derive lower bound from the range keyword.
        self::assertSame([1_700_000_000 - 7 * 86400, null], $service->window('7d', null, null));
        self::assertSame([null, null], $service->window('all', null, null));
    }

    public function testTotals(): void
    {
        $mapper = $this->createStub(ListenMapper::class);
        $mapper->method('countForUser')->willReturn(123);
        $mapper->method('countDistinctArtists')->willReturn(45);
        $mapper->method('getOldestListenedAt')->willReturn(1_600_000_000);

        $this->lovedMapper = $this->createStub(LovedMapper::class);
        $this->lovedMapper->method('countForUser')->willReturn(7);

        self::assertSame(
            ['listens' => 123, 'artists' => 45, 'loved' => 7, 'since' => 1_600_000_000],
            $this->service($mapper)->totals('alice'),
        );
    }
}
