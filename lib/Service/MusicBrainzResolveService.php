<?php

declare(strict_types=1);

namespace OCA\Earmark\Service;

use OCA\Earmark\Db\ListenMapper;
use OCA\Earmark\Db\MbCacheEntry;
use OCA\Earmark\Db\MbCacheMapper;
use OCA\Earmark\Db\Listen;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

/**
 * Resolves pending listens to MusicBrainz IDs. Pending listens are grouped by
 * `content_key` so each distinct track is looked up at most once; results are
 * cached in `earmark_mb_cache` and applied to every play sharing that key.
 * A confident match marks listens `resolved`; a miss marks them `unmatched`
 * (so they aren't retried every run). Work is bounded per run and network
 * lookups are rate-limited; cache hits are not throttled.
 */
class MusicBrainzResolveService
{
    private const BATCH_SIZE = 500;
    private const MAX_LOOKUPS_PER_RUN = 100;
    private const DEFAULT_THROTTLE_MICROSECONDS = 1_000_000; // 1 req/s — MusicBrainz limit

    public function __construct(
        private readonly ListenMapper $listenMapper,
        private readonly MbCacheMapper $cacheMapper,
        private readonly MusicBrainzService $musicBrainz,
        private readonly ITimeFactory $timeFactory,
        private readonly LoggerInterface $logger,
        private readonly int $throttleMicroseconds = self::DEFAULT_THROTTLE_MICROSECONDS,
    ) {
    }

    /**
     * Resolve one bounded batch of pending listens.
     *
     * @return int number of listens whose resolution state was updated
     */
    public function runSlice(): int
    {
        $pending = $this->listenMapper->findByState(Listen::STATE_PENDING, self::BATCH_SIZE);
        if ($pending === []) {
            return 0;
        }

        // One representative listen per distinct track identity.
        $representatives = [];
        foreach ($pending as $listen) {
            $representatives[$listen->getContentKey()] ??= $listen;
        }

        $now = $this->timeFactory->getTime();
        $lookups = 0;
        $updated = 0;

        foreach ($representatives as $contentKey => $listen) {
            $cache = $this->cacheMapper->findByKey($contentKey);

            if ($cache === null) {
                if ($lookups >= self::MAX_LOOKUPS_PER_RUN) {
                    break; // leave the rest pending for the next run
                }
                try {
                    $result = $this->musicBrainz->lookup(
                        $listen->getArtist(),
                        $listen->getTrack(),
                        $listen->getAlbum(),
                    );
                } catch (\Throwable $e) {
                    $this->logger->warning('Earmark: MusicBrainz lookup error for {key}: {msg}', [
                        'key' => $contentKey,
                        'msg' => $e->getMessage(),
                    ]);
                    continue; // stays pending; retried next run
                }
                $cache = $this->storeCache($contentKey, $result, $now);
                $lookups++;
                if ($this->throttleMicroseconds > 0) {
                    usleep($this->throttleMicroseconds);
                }
            }

            $state = $cache->getMatched() ? Listen::STATE_RESOLVED : Listen::STATE_UNMATCHED;
            $updated += $this->listenMapper->applyResolution(
                $contentKey,
                $cache->getArtistMbid(),
                $cache->getRecordingMbid(),
                $cache->getReleaseMbid(),
                $state,
                $now,
            );
        }

        return $updated;
    }

    /**
     * @param array{matched: bool, artistMbid: ?string, recordingMbid: ?string, releaseMbid: ?string} $result
     */
    private function storeCache(string $cacheKey, array $result, int $now): MbCacheEntry
    {
        $entry = new MbCacheEntry();
        $entry->setCacheKey($cacheKey);
        $entry->setArtistMbid($result['artistMbid']);
        $entry->setRecordingMbid($result['recordingMbid']);
        $entry->setReleaseMbid($result['releaseMbid']);
        $entry->setMatched($result['matched']);
        $entry->setResolvedAt($now);

        try {
            return $this->cacheMapper->insert($entry);
        } catch (Exception $e) {
            // A concurrent run cached the same key first — reuse theirs.
            if ($e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                $existing = $this->cacheMapper->findByKey($cacheKey);
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $e;
        }
    }
}
