<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Exception\LastfmException;
use OCA\Earmark\Scrobble\LastfmRecentTracks;
use PHPUnit\Framework\TestCase;

class LastfmRecentTracksTest extends TestCase
{
    private static function track(string $artist, string $name, ?string $uts, array $extra = []): array
    {
        $t = [
            'artist' => ['#text' => $artist, 'mbid' => ''],
            'name'   => $name,
            'album'  => ['#text' => '', 'mbid' => ''],
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
            'recenttracks' => [
                '@attr' => ['page' => '2', 'totalPages' => '500', 'total' => '99999'],
                'track' => [
                    self::track('The Beatles', 'Hey Jude', '1700000000'),
                    self::track('Bowie', 'Heroes', '1699999000'),
                ],
            ],
        ];

        $result = LastfmRecentTracks::parse($decoded);

        self::assertSame(2, $result['page']);
        self::assertSame(500, $result['totalPages']);
        self::assertSame(99999, $result['total']);
        self::assertCount(2, $result['listens']);
        self::assertSame('The Beatles', $result['listens'][0]->artist);
        self::assertSame(1_700_000_000, $result['listens'][0]->listenedAt);
    }

    public function testSkipsNowPlayingRow(): void
    {
        $nowPlaying = self::track('Live', 'Playing', null, ['@attr' => ['nowplaying' => 'true']]);
        $decoded = [
            'recenttracks' => [
                '@attr' => ['page' => '1', 'totalPages' => '1', 'total' => '1'],
                'track' => [$nowPlaying, self::track('A', 'One', '1700000000')],
            ],
        ];

        $result = LastfmRecentTracks::parse($decoded);

        self::assertCount(1, $result['listens']);
        self::assertSame('A', $result['listens'][0]->artist);
    }

    public function testCarriesThroughMbids(): void
    {
        $decoded = [
            'recenttracks' => [
                'track' => [
                    self::track('A', 'One', '1700000000', [
                        'artist' => ['mbid' => 'artist-mbid'],
                        'album'  => ['#text' => 'Album', 'mbid' => 'release-mbid'],
                        'mbid'   => 'recording-mbid',
                    ]),
                ],
            ],
        ];

        $listen = LastfmRecentTracks::parse($decoded)['listens'][0];

        self::assertSame('artist-mbid', $listen->artistMbid);
        self::assertSame('recording-mbid', $listen->recordingMbid);
        self::assertSame('release-mbid', $listen->releaseMbid);
        self::assertSame('Album', $listen->album);
    }

    public function testEmptyMbidsBecomeNull(): void
    {
        $listen = LastfmRecentTracks::parse([
            'recenttracks' => ['track' => [self::track('A', 'One', '1700000000')]],
        ])['listens'][0];

        self::assertNull($listen->artistMbid);
        self::assertNull($listen->recordingMbid);
        self::assertNull($listen->releaseMbid);
        self::assertNull($listen->album);
    }

    public function testHandlesSingleTrackObject(): void
    {
        $decoded = [
            'recenttracks' => [
                '@attr' => ['page' => '1', 'totalPages' => '1', 'total' => '1'],
                'track' => self::track('Solo', 'OnlyOne', '1700000000'),
            ],
        ];

        $result = LastfmRecentTracks::parse($decoded);

        self::assertCount(1, $result['listens']);
        self::assertSame('Solo', $result['listens'][0]->artist);
    }

    public function testEmptyHistoryYieldsNoListens(): void
    {
        $result = LastfmRecentTracks::parse([
            'recenttracks' => ['@attr' => ['total' => '0'], 'track' => []],
        ]);

        self::assertSame([], $result['listens']);
    }

    public function testThrowsOnApiError(): void
    {
        $this->expectException(LastfmException::class);
        LastfmRecentTracks::parse(['error' => 6, 'message' => 'User not found']);
    }

    public function testThrowsOnUnexpectedShape(): void
    {
        $this->expectException(LastfmException::class);
        LastfmRecentTracks::parse(['something' => 'else']);
    }
}
