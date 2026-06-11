<?php

declare(strict_types=1);

namespace OCA\Earmark\Scrobble;

use OCA\Earmark\Dto\IncomingListen;

/**
 * Parses an AudioScrobbler 1.2 submission body into a list of
 * {@see IncomingListen}. The protocol sends parallel indexed arrays:
 *
 *   a[0]=artist  t[0]=track  i[0]=unixTimestamp  b[0]=album  ...
 *
 * (PHP decodes `a[0]` form fields into a nested `a` array.) Rows missing an
 * artist, track or valid timestamp are skipped rather than failing the whole
 * batch. Pure / framework-free for unit testing.
 */
final class AudioScrobblerSubmission
{
    /**
     * @param array<string, mixed> $params the full request parameter map
     * @return list<IncomingListen>
     */
    public static function parse(array $params): array
    {
        $artists = self::toList($params['a'] ?? null);
        $tracks  = self::toList($params['t'] ?? null);
        $times   = self::toList($params['i'] ?? null);
        $albums  = self::toList($params['b'] ?? null);

        $listens = [];
        foreach ($artists as $idx => $artistRaw) {
            $artist = trim((string) $artistRaw);
            $track  = trim((string) ($tracks[$idx] ?? ''));
            if ($artist === '' || $track === '') {
                continue;
            }

            $timestamp = self::positiveInt($times[$idx] ?? null);
            if ($timestamp === null) {
                continue;
            }

            $album = isset($albums[$idx]) ? trim((string) $albums[$idx]) : '';
            $listens[] = new IncomingListen($artist, $track, $album !== '' ? $album : null, $timestamp);
        }

        return $listens;
    }

    /** Coerce a scalar or array param into a positionally-indexed list. */
    private static function toList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if ($value === null) {
            return [];
        }
        return [$value];
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && $value !== '0') {
            return (int) $value;
        }
        return null;
    }
}
