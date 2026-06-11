<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Exception\InvalidPayloadException;
use OCA\Earmark\Scrobble\ListenBrainzPayload;
use PHPUnit\Framework\TestCase;

class ListenBrainzPayloadTest extends TestCase
{
    private static function meta(string $artist, string $track, ?string $release = null): array
    {
        $m = ['artist_name' => $artist, 'track_name' => $track];
        if ($release !== null) {
            $m['release_name'] = $release;
        }
        return ['track_metadata' => $m];
    }

    public function testParsesSingleListen(): void
    {
        $item = self::meta('The Beatles', 'Hey Jude', 'Past Masters');
        $item['listened_at'] = 1_700_000_000;

        $result = ListenBrainzPayload::parse('single', [$item]);

        self::assertSame('single', $result['listenType']);
        self::assertCount(1, $result['listens']);
        $listen = $result['listens'][0];
        self::assertSame('The Beatles', $listen->artist);
        self::assertSame('Hey Jude', $listen->track);
        self::assertSame('Past Masters', $listen->album);
        self::assertSame(1_700_000_000, $listen->listenedAt);
    }

    public function testParsesImportWithMultipleListens(): void
    {
        $a = self::meta('A', 'One') + ['listened_at' => 100];
        $b = self::meta('B', 'Two') + ['listened_at' => 200];

        $result = ListenBrainzPayload::parse('import', [$a, $b]);

        self::assertCount(2, $result['listens']);
        self::assertNull($result['listens'][0]->album);
    }

    public function testPlayingNowHasNoTimestamp(): void
    {
        $result = ListenBrainzPayload::parse('playing_now', [self::meta('A', 'One')]);

        self::assertSame('playing_now', $result['listenType']);
        self::assertNull($result['listens'][0]->listenedAt);
    }

    public function testAcceptsNumericStringTimestamp(): void
    {
        $item = self::meta('A', 'One') + ['listened_at' => '1700000000'];
        $result = ListenBrainzPayload::parse('single', [$item]);
        self::assertSame(1_700_000_000, $result['listens'][0]->listenedAt);
    }

    public function testBlankReleaseBecomesNull(): void
    {
        $item = self::meta('A', 'One', '   ') + ['listened_at' => 1];
        $result = ListenBrainzPayload::parse('single', [$item]);
        self::assertNull($result['listens'][0]->album);
    }

    public function testRejectsUnknownListenType(): void
    {
        $this->expectException(InvalidPayloadException::class);
        ListenBrainzPayload::parse('bogus', [self::meta('A', 'One') + ['listened_at' => 1]]);
    }

    public function testRejectsEmptyPayload(): void
    {
        $this->expectException(InvalidPayloadException::class);
        ListenBrainzPayload::parse('import', []);
    }

    public function testRejectsMissingTrackMetadata(): void
    {
        $this->expectException(InvalidPayloadException::class);
        ListenBrainzPayload::parse('single', [['listened_at' => 1]]);
    }

    public function testRejectsBlankArtist(): void
    {
        $this->expectException(InvalidPayloadException::class);
        ListenBrainzPayload::parse('single', [self::meta('  ', 'One') + ['listened_at' => 1]]);
    }

    public function testRejectsMissingTimestampForSingle(): void
    {
        $this->expectException(InvalidPayloadException::class);
        ListenBrainzPayload::parse('single', [self::meta('A', 'One')]);
    }

    public function testRejectsZeroTimestamp(): void
    {
        $this->expectException(InvalidPayloadException::class);
        ListenBrainzPayload::parse('single', [self::meta('A', 'One') + ['listened_at' => 0]]);
    }
}
