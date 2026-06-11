<?php

declare(strict_types=1);

namespace OCA\Earmark\Scrobble;

use OCA\Earmark\Dto\IncomingListen;
use OCA\Earmark\Exception\InvalidPayloadException;

/**
 * Parses a ListenBrainz-compatible `submit-listens` request body into a list
 * of {@see IncomingListen}. Mirrors the public ListenBrainz API shape:
 *
 *   { "listen_type": "single" | "import" | "playing_now",
 *     "payload": [ { "listened_at": 1700000000,
 *                    "track_metadata": { "artist_name": "...",
 *                                        "track_name": "...",
 *                                        "release_name": "..." } } ] }
 *
 * Pure / framework-free so it can be unit-tested directly.
 */
final class ListenBrainzPayload
{
    public const TYPE_SINGLE = 'single';
    public const TYPE_IMPORT = 'import';
    public const TYPE_PLAYING_NOW = 'playing_now';

    private const TYPES = [self::TYPE_SINGLE, self::TYPE_IMPORT, self::TYPE_PLAYING_NOW];

    /**
     * @return array{listenType: string, listens: list<IncomingListen>}
     * @throws InvalidPayloadException
     */
    public static function parse(mixed $listenType, mixed $payload): array
    {
        if (!is_string($listenType) || !in_array($listenType, self::TYPES, true)) {
            throw new InvalidPayloadException('invalid or missing listen_type');
        }
        if (!is_array($payload) || $payload === []) {
            throw new InvalidPayloadException('payload must be a non-empty array');
        }

        $expectTimestamp = $listenType !== self::TYPE_PLAYING_NOW;
        $listens = [];

        foreach ($payload as $item) {
            if (!is_array($item)) {
                throw new InvalidPayloadException('each listen must be an object');
            }

            $meta = $item['track_metadata'] ?? null;
            if (!is_array($meta)) {
                throw new InvalidPayloadException('track_metadata is required');
            }

            $artist = trim((string) ($meta['artist_name'] ?? ''));
            $track  = trim((string) ($meta['track_name'] ?? ''));
            if ($artist === '' || $track === '') {
                throw new InvalidPayloadException('artist_name and track_name are required');
            }

            $releaseRaw = isset($meta['release_name']) ? trim((string) $meta['release_name']) : '';
            $album = $releaseRaw !== '' ? $releaseRaw : null;

            $listenedAt = null;
            if ($expectTimestamp) {
                $listenedAt = self::parseTimestamp($item['listened_at'] ?? null);
            }

            $listens[] = new IncomingListen($artist, $track, $album, $listenedAt);
        }

        return ['listenType' => $listenType, 'listens' => $listens];
    }

    /** @throws InvalidPayloadException */
    private static function parseTimestamp(mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        // JSON from some clients arrives as a numeric string.
        if (is_string($value) && ctype_digit($value) && $value !== '0') {
            return (int) $value;
        }
        throw new InvalidPayloadException('listened_at must be a positive Unix timestamp');
    }
}
