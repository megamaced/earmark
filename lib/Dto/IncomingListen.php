<?php

declare(strict_types=1);

namespace OCA\Earmark\Dto;

/**
 * A listen parsed from an inbound request or an import, before it is
 * persisted. Carries raw text and an optional Unix timestamp (`listenedAt`
 * is null for a "now playing" notification, which is accepted but not
 * stored). MBIDs are populated only by sources that already supply them
 * (e.g. Last.fm); otherwise they are null and the listen is resolved later.
 */
final class IncomingListen
{
    public function __construct(
        public readonly string $artist,
        public readonly string $track,
        public readonly ?string $album,
        public readonly ?int $listenedAt,
        public readonly ?string $artistMbid = null,
        public readonly ?string $recordingMbid = null,
        public readonly ?string $releaseMbid = null,
    ) {
    }
}
