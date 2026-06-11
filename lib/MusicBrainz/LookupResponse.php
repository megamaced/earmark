<?php

declare(strict_types=1);

namespace OCA\Earmark\MusicBrainz;

/**
 * Parses a ListenBrainz `metadata/lookup` response into MBIDs. A miss is a
 * 200 with an empty object, so "matched" is derived from whether any MBID is
 * present rather than from the HTTP status. Pure / framework-free.
 *
 * Response shape (on a hit):
 *   { "artist_mbids": ["..."], "recording_mbid": "...", "release_mbid": "...", ... }
 */
final class LookupResponse
{
    /**
     * @param array<string, mixed> $decoded
     * @return array{matched: bool, artistMbid: ?string, recordingMbid: ?string, releaseMbid: ?string}
     */
    public static function parse(array $decoded): array
    {
        $recordingMbid = self::str($decoded['recording_mbid'] ?? null);
        $releaseMbid   = self::str($decoded['release_mbid'] ?? null);

        $artistMbid = null;
        $artistMbids = $decoded['artist_mbids'] ?? null;
        if (is_array($artistMbids) && isset($artistMbids[0])) {
            $artistMbid = self::str($artistMbids[0]);
        }

        return [
            'matched'       => $recordingMbid !== null || $releaseMbid !== null || $artistMbid !== null,
            'artistMbid'    => $artistMbid,
            'recordingMbid' => $recordingMbid,
            'releaseMbid'   => $releaseMbid,
        ];
    }

    private static function str(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value !== '' ? $value : null;
    }
}
