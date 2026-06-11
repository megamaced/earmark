<?php

declare(strict_types=1);

namespace OCA\Earmark\Service;

use OCA\Earmark\Db\Listen;
use OCA\Earmark\Db\ListenMapper;
use OCA\Earmark\Normalize;
use OCA\Earmark\Source;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * The single ingest path for a recorded play, shared by the inbound scrobble
 * API and (later) the Last.fm / Spotify importers. Derives the dedup and
 * content keys, then inserts idempotently — a play that already exists for
 * the user is silently skipped.
 */
class ListenIngestService
{
    private const MAX_LEN = 512;

    public function __construct(
        private readonly ListenMapper $mapper,
        private readonly ITimeFactory $timeFactory,
    ) {
    }

    /**
     * @return bool true if a new listen was stored, false if it was a duplicate
     * @throws \InvalidArgumentException on missing/invalid fields
     */
    public function ingest(
        string $userId,
        string $artist,
        string $track,
        ?string $album,
        int $listenedAt,
        string $source,
    ): bool {
        $artist = trim($artist);
        $track  = trim($track);
        $album  = ($album !== null && trim($album) !== '') ? trim($album) : null;

        if ($artist === '' || $track === '') {
            throw new \InvalidArgumentException('artist and track are required');
        }
        if ($listenedAt <= 0) {
            throw new \InvalidArgumentException('listenedAt must be a positive Unix timestamp');
        }
        if (!Source::isValid($source)) {
            throw new \InvalidArgumentException('unknown source: ' . $source);
        }

        $contentKey = Normalize::contentKey($artist, $track, $album);

        $listen = new Listen();
        $listen->setUserId($userId);
        $listen->setArtist(self::clamp($artist));
        $listen->setTrack(self::clamp($track));
        $listen->setAlbum($album !== null ? self::clamp($album) : null);
        $listen->setContentKey($contentKey);
        $listen->setDedupHash(Normalize::dedupHash($contentKey, $listenedAt));
        $listen->setListenedAt($listenedAt);
        $listen->setSource($source);
        $listen->setResolutionState(Listen::STATE_PENDING);
        $listen->setCreatedAt($this->timeFactory->getTime());

        return $this->mapper->createIfNew($listen);
    }

    private static function clamp(string $value): string
    {
        return mb_substr($value, 0, self::MAX_LEN);
    }
}
