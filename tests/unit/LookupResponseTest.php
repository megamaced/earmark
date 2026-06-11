<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\MusicBrainz\LookupResponse;
use PHPUnit\Framework\TestCase;

class LookupResponseTest extends TestCase
{
    public function testParsesFullMatch(): void
    {
        $result = LookupResponse::parse([
            'artist_mbids'   => ['artist-1', 'artist-2'],
            'recording_mbid' => 'rec-1',
            'release_mbid'   => 'rel-1',
        ]);

        self::assertTrue($result['matched']);
        self::assertSame('artist-1', $result['artistMbid']);
        self::assertSame('rec-1', $result['recordingMbid']);
        self::assertSame('rel-1', $result['releaseMbid']);
    }

    public function testEmptyObjectIsNotMatched(): void
    {
        $result = LookupResponse::parse([]);

        self::assertFalse($result['matched']);
        self::assertNull($result['artistMbid']);
        self::assertNull($result['recordingMbid']);
        self::assertNull($result['releaseMbid']);
    }

    public function testMatchedWhenOnlyArtistMbidPresent(): void
    {
        $result = LookupResponse::parse(['artist_mbids' => ['artist-1']]);

        self::assertTrue($result['matched']);
        self::assertSame('artist-1', $result['artistMbid']);
        self::assertNull($result['recordingMbid']);
    }

    public function testBlankAndNonStringValuesBecomeNull(): void
    {
        $result = LookupResponse::parse([
            'artist_mbids'   => [''],
            'recording_mbid' => '   ',
            'release_mbid'   => 12345,
        ]);

        self::assertFalse($result['matched']);
        self::assertNull($result['artistMbid']);
        self::assertNull($result['recordingMbid']);
        self::assertNull($result['releaseMbid']);
    }
}
