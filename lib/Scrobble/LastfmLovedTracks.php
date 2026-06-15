<?php

declare(strict_types=1);

namespace OCA\Earmark\Scrobble;

use OCA\Earmark\Exception\LastfmException;

/**
 * Parses a decoded `user.getLovedTracks` JSON response into loved-track rows
 * plus pagination metadata. Carries through the recording MBID Last.fm
 * supplies, and tolerates the API's quirk of returning a single track as an
 * object rather than an array.
 *
 * Pure / framework-free so it can be unit-tested directly.
 */
final class LastfmLovedTracks
{
    /**
     * @param array<string, mixed> $decoded json_decode(..., true) of the response
     * @return array{
     *     page: int,
     *     totalPages: int,
     *     total: int,
     *     loved: list<array{artist: string, track: string, recordingMbid: ?string, lovedAt: int}>
     * }
     * @throws LastfmException
     */
    public static function parse(array $decoded): array
    {
        if (isset($decoded['error'])) {
            $message = (string) ($decoded['message'] ?? ('Last.fm error ' . $decoded['error']));
            throw new LastfmException($message);
        }

        $loved = $decoded['lovedtracks'] ?? null;
        if (!is_array($loved)) {
            throw new LastfmException('unexpected Last.fm response shape');
        }

        $attr = is_array($loved['@attr'] ?? null) ? $loved['@attr'] : [];

        $tracks = $loved['track'] ?? [];
        if (!is_array($tracks)) {
            $tracks = [];
        }
        // A single track is returned as an object, not a list.
        if (array_key_exists('name', $tracks)) {
            $tracks = [$tracks];
        }

        $rows = [];
        foreach ($tracks as $track) {
            if (!is_array($track)) {
                continue;
            }

            $artist = trim((string) ($track['artist']['name'] ?? $track['artist']['#text'] ?? ''));
            $name   = trim((string) ($track['name'] ?? ''));
            if ($artist === '' || $name === '') {
                continue;
            }

            $uts = $track['date']['uts'] ?? null;

            $rows[] = [
                'artist'        => $artist,
                'track'         => $name,
                'recordingMbid' => self::mbid($track['mbid'] ?? null),
                'lovedAt'       => self::isPositiveIntLike($uts) ? (int) $uts : 0,
            ];
        }

        return [
            'page'       => (int) ($attr['page'] ?? 1),
            'totalPages' => (int) ($attr['totalPages'] ?? 0),
            'total'      => (int) ($attr['total'] ?? 0),
            'loved'      => $rows,
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
