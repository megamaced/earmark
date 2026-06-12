<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Db\Listen;
use OCA\Earmark\Db\ListenMapper;
use OCA\Earmark\Db\MbCacheEntry;
use OCA\Earmark\Db\MbCacheMapper;
use OCA\Earmark\Exception\MusicBrainzException;
use OCA\Earmark\Service\MusicBrainzResolveService;
use OCA\Earmark\Service\MusicBrainzService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MusicBrainzResolveServiceTest extends TestCase
{
    private function listen(string $contentKey, string $artist, string $track, ?string $album): Listen
    {
        $listen = new Listen();
        $listen->setContentKey($contentKey);
        $listen->setArtist($artist);
        $listen->setTrack($track);
        $listen->setAlbum($album);
        return $listen;
    }

    public function testGroupsByContentKeyAndLooksUpEachTrackOnce(): void
    {
        // Two pending plays of the same track (same content key) + one different.
        $a1 = $this->listen('ck1', 'The Beatles', 'Hey Jude', 'Past Masters');
        $a2 = $this->listen('ck1', 'The Beatles', 'Hey Jude', 'Past Masters');
        $b1 = $this->listen('ck2', 'Bowie', 'Heroes', null);

        $listenMapper = $this->createMock(ListenMapper::class);
        $listenMapper->method('findByState')->willReturn([$a1, $a2, $b1]);
        // One applyResolution per distinct content key → two calls total.
        $listenMapper->expects($this->exactly(2))->method('applyResolution')->willReturn(2);

        $musicBrainz = $this->createMock(MusicBrainzService::class);
        // Exactly two lookups despite three pending listens (ck1 looked up once).
        $musicBrainz->expects($this->exactly(2))->method('lookup')->willReturn([
            'matched'       => true,
            'artistMbid'    => 'am',
            'recordingMbid' => 'rm',
            'releaseMbid'   => 'rl',
        ]);

        $cacheMapper = $this->createStub(MbCacheMapper::class);
        $cacheMapper->method('findByKey')->willReturn(null);
        $cacheMapper->method('insert')->willReturnArgument(0);

        $updated = $this->makeService($listenMapper, $cacheMapper, $musicBrainz)->runSlice();

        self::assertSame(4, $updated); // 2 + 2
    }

    public function testCacheHitSkipsLookup(): void
    {
        $listen = $this->listen('ck1', 'A', 'One', null);

        $listenMapper = $this->createMock(ListenMapper::class);
        $listenMapper->method('findByState')->willReturn([$listen]);
        $listenMapper->expects($this->once())->method('applyResolution')
            ->with('ck1', 'cached-artist', 'cached-rec', null, Listen::STATE_RESOLVED, 1000)
            ->willReturn(1);

        $cached = new MbCacheEntry();
        $cached->setCacheKey('ck1');
        $cached->setArtistMbid('cached-artist');
        $cached->setRecordingMbid('cached-rec');
        $cached->setReleaseMbid(null);
        $cached->setMatched(true);
        $cached->setResolvedAt(500);

        $cacheMapper = $this->createStub(MbCacheMapper::class);
        $cacheMapper->method('findByKey')->willReturn($cached);

        $musicBrainz = $this->createMock(MusicBrainzService::class);
        $musicBrainz->expects($this->never())->method('lookup');

        $updated = $this->makeService($listenMapper, $cacheMapper, $musicBrainz)->runSlice();

        self::assertSame(1, $updated);
    }

    public function testUnmatchedCacheMarksListensUnmatched(): void
    {
        $listen = $this->listen('ck1', 'Obscure', 'Track', null);

        $listenMapper = $this->createMock(ListenMapper::class);
        $listenMapper->method('findByState')->willReturn([$listen]);
        $listenMapper->expects($this->once())->method('applyResolution')
            ->with('ck1', null, null, null, Listen::STATE_UNMATCHED, 1000)
            ->willReturn(1);

        $musicBrainz = $this->createMock(MusicBrainzService::class);
        $musicBrainz->expects($this->once())->method('lookup')->willReturn([
            'matched'       => false,
            'artistMbid'    => null,
            'recordingMbid' => null,
            'releaseMbid'   => null,
        ]);

        $cacheMapper = $this->createStub(MbCacheMapper::class);
        $cacheMapper->method('findByKey')->willReturn(null);
        $cacheMapper->method('insert')->willReturnArgument(0);

        $this->makeService($listenMapper, $cacheMapper, $musicBrainz)->runSlice();
    }

    public function testStopsRunOnRateLimit(): void
    {
        $a = $this->listen('ck1', 'A', 'One', null);
        $b = $this->listen('ck2', 'B', 'Two', null);

        $listenMapper = $this->createMock(ListenMapper::class);
        $listenMapper->method('findByState')->willReturn([$a, $b]);
        // A 429 stops the run before any resolution is applied.
        $listenMapper->expects($this->never())->method('applyResolution');

        $musicBrainz = $this->createMock(MusicBrainzService::class);
        // Only the first key is attempted; the run breaks on the 429.
        $musicBrainz->expects($this->once())->method('lookup')
            ->willThrowException(new MusicBrainzException('rate limit', 429));

        $cacheMapper = $this->createStub(MbCacheMapper::class);
        $cacheMapper->method('findByKey')->willReturn(null);

        self::assertSame(0, $this->makeService($listenMapper, $cacheMapper, $musicBrainz)->runSlice());
    }

    public function testNoPendingListensIsANoop(): void
    {
        $listenMapper = $this->createMock(ListenMapper::class);
        $listenMapper->method('findByState')->willReturn([]);
        $listenMapper->expects($this->never())->method('applyResolution');

        $musicBrainz = $this->createMock(MusicBrainzService::class);
        $musicBrainz->expects($this->never())->method('lookup');

        $service = $this->makeService($listenMapper, $this->createStub(MbCacheMapper::class), $musicBrainz);
        self::assertSame(0, $service->runSlice());
    }

    private function makeService(
        ListenMapper $listenMapper,
        MbCacheMapper $cacheMapper,
        MusicBrainzService $musicBrainz,
    ): MusicBrainzResolveService {
        $time = $this->createStub(ITimeFactory::class);
        $time->method('getTime')->willReturn(1000);

        return new MusicBrainzResolveService(
            $listenMapper,
            $cacheMapper,
            $musicBrainz,
            $time,
            $this->createStub(LoggerInterface::class),
            0, // no throttle in tests
        );
    }
}
