<?php

declare(strict_types=1);

namespace OCA\Earmark\Service;

use OCA\Earmark\Db\ListenMapper;
use OCA\Earmark\Stats\Range;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Computes listening statistics for a user. Top-N queries aggregate in the
 * database; the listening clock buckets raw timestamps in PHP so it stays
 * portable across MySQL and PostgreSQL.
 */
class StatsService
{
    public function __construct(
        private readonly ListenMapper $listenMapper,
        private readonly ITimeFactory $timeFactory,
    ) {
    }

    /**
     * Top artists/albums/tracks for a user. A range keyword sets the window,
     * unless explicit `from`/`to` timestamps are given (the custom range used
     * by the Library), in which case they take precedence.
     *
     * @return array<int, array<string, mixed>>
     */
    public function top(
        string $userId,
        string $type,
        string $range,
        int $limit = 20,
        int $offset = 0,
        ?int $from = null,
        ?int $to = null,
    ): array {
        [$lo, $hi] = $this->window($range, $from, $to);

        return match ($type) {
            'artist' => $this->listenMapper->topArtists($userId, $lo, $hi, $limit, $offset),
            'album'  => $this->listenMapper->topAlbums($userId, $lo, $hi, $limit, $offset),
            'track'  => $this->listenMapper->topTracks($userId, $lo, $hi, $limit, $offset),
            default  => [],
        };
    }

    /**
     * Plays per hour-of-day (UTC), as a fixed 24-element array indexed 0–23.
     *
     * @return list<int>
     */
    public function clock(string $userId, string $range, ?int $from = null, ?int $to = null): array
    {
        [$lo, $hi] = $this->window($range, $from, $to);

        $buckets = array_fill(0, 24, 0);
        foreach ($this->listenMapper->listenedAtSince($userId, $lo, $hi) as $timestamp) {
            $hour = (int) gmdate('G', $timestamp);
            $buckets[$hour]++;
        }

        return $buckets;
    }

    /**
     * Scrobble counts per calendar year (UTC), oldest first — the Library's
     * lifetime breakdown chart. Bucketed in PHP to stay DB-portable.
     *
     * @return list<array{year: int, count: int}>
     */
    public function perYear(string $userId): array
    {
        $buckets = [];
        foreach ($this->listenMapper->listenedAtSince($userId, null) as $timestamp) {
            $year = (int) gmdate('Y', $timestamp);
            $buckets[$year] = ($buckets[$year] ?? 0) + 1;
        }
        ksort($buckets);

        $out = [];
        foreach ($buckets as $year => $count) {
            $out[] = ['year' => $year, 'count' => $count];
        }

        return $out;
    }

    /**
     * Resolve a stats window to [from, to] timestamps. Explicit bounds (custom
     * range) win; otherwise the range keyword sets a lower bound with no upper.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function window(string $range, ?int $from, ?int $to): array
    {
        if ($from !== null || $to !== null) {
            return [$from, $to];
        }

        return [Range::fromTimestamp(Range::normalize($range), $this->timeFactory->getTime()), null];
    }

    /**
     * @return array{listens: int, artists: int, since: int|null}
     */
    public function totals(string $userId): array
    {
        return [
            'listens' => $this->listenMapper->countForUser($userId),
            'artists' => $this->listenMapper->countDistinctArtists($userId),
            'since'   => $this->listenMapper->getOldestListenedAt($userId),
        ];
    }
}
