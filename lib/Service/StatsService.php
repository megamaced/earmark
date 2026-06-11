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
     * @return array<int, array<string, mixed>>
     */
    public function top(string $userId, string $type, string $range, int $limit = 20): array
    {
        $from = Range::fromTimestamp(Range::normalize($range), $this->timeFactory->getTime());

        return match ($type) {
            'artist' => $this->listenMapper->topArtists($userId, $from, $limit),
            'album'  => $this->listenMapper->topAlbums($userId, $from, $limit),
            'track'  => $this->listenMapper->topTracks($userId, $from, $limit),
            default  => [],
        };
    }

    /**
     * Plays per hour-of-day (UTC), as a fixed 24-element array indexed 0–23.
     *
     * @return list<int>
     */
    public function clock(string $userId, string $range): array
    {
        $from = Range::fromTimestamp(Range::normalize($range), $this->timeFactory->getTime());

        $buckets = array_fill(0, 24, 0);
        foreach ($this->listenMapper->listenedAtSince($userId, $from) as $timestamp) {
            $hour = (int) gmdate('G', $timestamp);
            $buckets[$hour]++;
        }

        return $buckets;
    }

    /**
     * @return array{listens: int, since: int|null}
     */
    public function totals(string $userId): array
    {
        return [
            'listens' => $this->listenMapper->countForUser($userId),
            'since'   => $this->listenMapper->getOldestListenedAt($userId),
        ];
    }
}
