<?php

declare(strict_types=1);

namespace OCA\Earmark\Scrobble;

use OCA\Earmark\Dto\IncomingListen;
use OCA\Earmark\Exception\LastfmException;

/**
 * Parses a decoded `user.getRecentTracks` JSON response into listens plus
 * pagination metadata. Skips the live "now playing" row (it has no `date`),
 * carries through any MBIDs Last.fm supplies, and tolerates the API's quirk
 * of returning a single track as an object rather than an array.
 *
 * Pure / framework-free so it can be unit-tested directly.
 */
final class LastfmRecentTracks
{
    /**
     * @param array<string, mixed> $decoded json_decode(..., true) of the response
     * @return array{page: int, totalPages: int, total: int, listens: list<IncomingListen>}
     * @throws LastfmException
     */
    public static function parse(array $decoded): array
    {
        if (isset($decoded['error'])) {
            $message = (string) ($decoded['message'] ?? ('Last.fm error ' . $decoded['error']));
            throw new LastfmException($message);
        }

        $recent = $decoded['recenttracks'] ?? null;
        if (!is_array($recent)) {
            throw new LastfmException('unexpected Last.fm response shape');
        }

        $attr = is_array($recent['@attr'] ?? null) ? $recent['@attr'] : [];

        $tracks = $recent['track'] ?? [];
        if (!is_array($tracks)) {
            $tracks = [];
        }
        // A single track is returned as an object, not a list.
        if (array_key_exists('name', $tracks)) {
            $tracks = [$tracks];
        }

        $listens = [];
        foreach ($tracks as $track) {
            if (!is_array($track)) {
                continue;
            }
            // The now-playing row has @attr.nowplaying = "true" and no date.
            if (($track['@attr']['nowplaying'] ?? null) === 'true') {
                continue;
            }

            $uts = $track['date']['uts'] ?? null;
            if (!self::isPositiveIntLike($uts)) {
                continue;
            }

            $artist = trim((string) ($track['artist']['#text'] ?? $track['artist']['name'] ?? ''));
            $name   = trim((string) ($track['name'] ?? ''));
            if ($artist === '' || $name === '') {
                continue;
            }

            $albumText = trim((string) ($track['album']['#text'] ?? ''));

            $listens[] = new IncomingListen(
                $artist,
                $name,
                $albumText !== '' ? $albumText : null,
                (int) $uts,
                self::mbid($track['artist']['mbid'] ?? null),
                self::mbid($track['mbid'] ?? null),
                self::mbid($track['album']['mbid'] ?? null),
            );
        }

        return [
            'page'       => (int) ($attr['page'] ?? 1),
            'totalPages' => (int) ($attr['totalPages'] ?? 0),
            'total'      => (int) ($attr['total'] ?? 0),
            'listens'    => $listens,
        ];
    }

    private static function isPositiveIntLike(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }
        return is_string($value) && ctype_digit($value) && $value !== '0';
    }

    private static function mbid(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value !== '' ? $value : null;
    }
}
