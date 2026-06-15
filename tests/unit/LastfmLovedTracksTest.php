<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Exception\LastfmException;
use OCA\Earmark\Scrobble\LastfmLovedTracks;
use PHPUnit\Framework\TestCase;

class LastfmLovedTracksTest extends TestCase
{
    private static function track(string $artist, string $name, ?string $uts, array $extra = []): array
    {
        $t = [
            'artist' => ['name' => $artist, 'mbid' => ''],
            'name'   => $name,
            'mbid'   => '',
        ];
        if ($uts !== null) {
            $t['date'] = ['uts' => $uts, '#text' => 'whenever'];
        }
        return array_replace_recursive($t, $extra);
    }

    public function testParsesPageWithMetadata(): void
    {
        $decoded = [
            'lovedtracks' => [
                '@attr' => ['page' => '1', 'totalPages' => '3', 'total' => '120'],
                'track' => [
                    self::track('The Beatles', 'Hey Jude', '1700000000'),
                    self::track('Bowie', 'Heroes', '1699999000'),
                ],
            ],
        ];

        $result = LastfmLovedTracks::parse($decoded);

        self::assertSame(1, $result['page']);
        self::assertSame(3, $result['totalPages']);
        self::assertSame(120, $result['total']);
        self::assertCount(2, $result['loved']);
        self::assertSame('The Beatles', $result['loved'][0]['artist']);
        self::assertSame('Hey Jude', $result['loved'][0]['track']);
        self::assertSame(1_700_000_000, $result['loved'][0]['lovedAt']);
    }

    public function testCarriesThroughRecordingMbid(): void
    {
        $row = LastfmLovedTracks::parse([
            'lovedtracks' => [
                'track' => [self::track('A', 'One', '1700000000', ['mbid' => 'recording-mbid'])],
            ],
        ])['loved'][0];

        self::assertSame('recording-mbid', $row['recordingMbid']);
    }

    public function testEmptyMbidBecomesNullAndMissingDateIsZero(): void
    {
        $row = LastfmLovedTracks::parse([
            'lovedtracks' => ['track' => [self::track('A', 'One', null)]],
        ])['loved'][0];

        self::assertNull($row['recordingMbid']);
        self::assertSame(0, $row['lovedAt']);
    }

    public function testHandlesSingleTrackObject(): void
    {
        $result = LastfmLovedTracks::parse([
            'lovedtracks' => [
                '@attr' => ['page' => '1', 'totalPages' => '1', 'total' => '1'],
                'track' => self::track('Solo', 'OnlyOne', '1700000000'),
            ],
        ]);

        self::assertCount(1, $result['loved']);
        self::assertSame('Solo', $result['loved'][0]['artist']);
    }

    public function testEmptyLovedListYieldsNoRows(): void
    {
        $result = LastfmLovedTracks::parse([
            'lovedtracks' => ['@attr' => ['total' => '0'], 'track' => []],
        ]);

        self::assertSame([], $result['loved']);
    }

    public function testThrowsOnApiError(): void
    {
        $this->expectException(LastfmException::class);
        LastfmLovedTracks::parse(['error' => 6, 'message' => 'User not found']);
    }

    public function testThrowsOnUnexpectedShape(): void
    {
        $this->expectException(LastfmException::class);
        LastfmLovedTracks::parse(['something' => 'else']);
    }
}
