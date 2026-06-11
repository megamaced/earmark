<?php

declare(strict_types=1);

namespace OCA\Earmark;

/**
 * Pure text-normalisation helpers shared by ingest and MusicBrainz
 * resolution. Two derived keys are produced from a listen's raw text:
 *
 *   contentKey  — sha1 of normalised artist|track|album. Identifies a
 *                 track regardless of spelling/casing/whitespace, so all
 *                 listens of the same track share it. Also the cache key
 *                 for a MusicBrainz resolution.
 *   dedupHash   — sha1 of contentKey|listenedAt. Identifies a single play,
 *                 making (user_id, dedupHash) a stable idempotency key so
 *                 re-imports never duplicate.
 *
 * Hashing keeps the unique/lookup indexes short and avoids MySQL's
 * index-length limits on long artist/track strings. The hash is computed
 * from the *raw* scrobble text, so it stays stable even after MusicBrainz
 * resolution rewrites the canonical names.
 */
final class Normalize
{
    private const SEP = "\x1f"; // ASCII unit separator — can't occur in normal text

    /** Lowercase, trim, and collapse internal whitespace runs to single spaces. */
    public static function text(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        return mb_strtolower($collapsed, 'UTF-8');
    }

    /** Stable identity for a track (artist + track + album), independent of listen time. */
    public static function contentKey(string $artist, string $track, ?string $album): string
    {
        $parts = [
            self::text($artist),
            self::text($track),
            self::text($album ?? ''),
        ];
        return sha1(implode(self::SEP, $parts));
    }

    /** Stable identity for a single play — used for (user_id, dedupHash) idempotency. */
    public static function dedupHash(string $contentKey, int $listenedAt): string
    {
        return sha1($contentKey . self::SEP . $listenedAt);
    }
}
