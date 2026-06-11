<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Db\Listen;
use OCA\Earmark\Db\ListenMapper;
use OCA\Earmark\Service\ListenIngestService;
use OCA\Earmark\Source;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

class ListenIngestServiceTest extends TestCase
{
    private ?Listen $captured = null;

    private function service(): ListenIngestService
    {
        $this->captured = null;
        $mapper = $this->createStub(ListenMapper::class);
        $mapper->method('createIfNew')->willReturnCallback(function (Listen $listen): bool {
            $this->captured = $listen;
            return true;
        });
        $time = $this->createStub(ITimeFactory::class);
        $time->method('getTime')->willReturn(1000);

        return new ListenIngestService($mapper, $time);
    }

    public function testStoresResolvedWhenMbidsPresent(): void
    {
        $stored = $this->service()->ingest(
            'alice',
            'The Beatles',
            'Hey Jude',
            'Past Masters',
            1_700_000_000,
            Source::LASTFM,
            'artist-mbid',
            null,
            'release-mbid',
        );

        self::assertTrue($stored);
        self::assertSame(Listen::STATE_RESOLVED, $this->captured->getResolutionState());
        self::assertSame('artist-mbid', $this->captured->getArtistMbid());
        self::assertNull($this->captured->getRecordingMbid());
        self::assertSame('release-mbid', $this->captured->getReleaseMbid());
        self::assertSame(1000, $this->captured->getResolvedAt());
        self::assertSame(Source::LASTFM, $this->captured->getSource());
    }

    public function testStoresPendingWhenNoMbids(): void
    {
        $this->service()->ingest('alice', 'A', 'One', null, 1_700_000_000, Source::SCROBBLE);

        self::assertSame(Listen::STATE_PENDING, $this->captured->getResolutionState());
        self::assertNull($this->captured->getResolvedAt());
        self::assertNull($this->captured->getArtistMbid());
    }

    public function testBlankMbidsCountAsNoMbids(): void
    {
        $this->service()->ingest('alice', 'A', 'One', null, 1_700_000_000, Source::LASTFM, '', '  ', '');

        self::assertSame(Listen::STATE_PENDING, $this->captured->getResolutionState());
    }

    public function testDerivesDedupAndContentKeys(): void
    {
        $this->service()->ingest('alice', 'A', 'One', null, 1_700_000_000, Source::MANUAL);

        self::assertSame(40, strlen($this->captured->getContentKey()));
        self::assertSame(40, strlen($this->captured->getDedupHash()));
    }

    public function testRejectsBlankArtist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->ingest('alice', '   ', 'One', null, 1_700_000_000, Source::LASTFM);
    }

    public function testRejectsInvalidSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->ingest('alice', 'A', 'One', null, 1_700_000_000, 'bogus');
    }

    public function testRejectsNonPositiveTimestamp(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->ingest('alice', 'A', 'One', null, 0, Source::LASTFM);
    }
}
