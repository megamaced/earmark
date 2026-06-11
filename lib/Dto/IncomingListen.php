<?php

declare(strict_types=1);

namespace OCA\Earmark\Dto;

/**
 * A listen parsed from an inbound request, before it is persisted. Carries
 * only raw text and an optional Unix timestamp; `listenedAt` is null for a
 * "now playing" notification, which is accepted but not stored.
 */
final class IncomingListen
{
    public function __construct(
        public readonly string $artist,
        public readonly string $track,
        public readonly ?string $album,
        public readonly ?int $listenedAt,
    ) {
    }
}
