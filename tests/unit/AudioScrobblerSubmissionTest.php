<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Scrobble\AudioScrobblerSubmission;
use PHPUnit\Framework\TestCase;

class AudioScrobblerSubmissionTest extends TestCase
{
    public function testParsesMultipleRows(): void
    {
        $params = [
            's' => 'session',
            'a' => ['The Beatles', 'Bowie'],
            't' => ['Hey Jude', 'Heroes'],
            'b' => ['Past Masters', 'Heroes'],
            'i' => ['1700000000', '1700000100'],
        ];

        $listens = AudioScrobblerSubmission::parse($params);

        self::assertCount(2, $listens);
        self::assertSame('The Beatles', $listens[0]->artist);
        self::assertSame('Hey Jude', $listens[0]->track);
        self::assertSame('Past Masters', $listens[0]->album);
        self::assertSame(1_700_000_000, $listens[0]->listenedAt);
        self::assertSame('Bowie', $listens[1]->artist);
    }

    public function testSkipsRowMissingTimestamp(): void
    {
        $params = [
            'a' => ['A', 'B'],
            't' => ['One', 'Two'],
            'i' => ['1700000000', ''],
        ];

        $listens = AudioScrobblerSubmission::parse($params);

        self::assertCount(1, $listens);
        self::assertSame('A', $listens[0]->artist);
    }

    public function testSkipsRowMissingArtistOrTrack(): void
    {
        $params = [
            'a' => ['', 'B'],
            't' => ['One', ''],
            'i' => ['1700000000', '1700000001'],
        ];

        self::assertCount(0, AudioScrobblerSubmission::parse($params));
    }

    public function testAlbumIsOptional(): void
    {
        $params = [
            'a' => ['A'],
            't' => ['One'],
            'i' => ['1700000000'],
        ];

        $listens = AudioScrobblerSubmission::parse($params);

        self::assertCount(1, $listens);
        self::assertNull($listens[0]->album);
    }

    public function testCoercesSingleScalarRow(): void
    {
        // A non-array single submission (a=..., t=..., i=...).
        $params = ['a' => 'A', 't' => 'One', 'i' => '1700000000'];

        $listens = AudioScrobblerSubmission::parse($params);

        self::assertCount(1, $listens);
        self::assertSame('A', $listens[0]->artist);
        self::assertSame(1_700_000_000, $listens[0]->listenedAt);
    }

    public function testEmptyParamsYieldNoListens(): void
    {
        self::assertSame([], AudioScrobblerSubmission::parse(['s' => 'x']));
    }
}
