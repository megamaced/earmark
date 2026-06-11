<?php

declare(strict_types=1);

namespace OCA\Earmark;

/**
 * Canonical sources a listen can originate from. Central constant list so
 * ingest paths and stats filters agree on the same vocabulary.
 */
final class Source
{
    /** Imported from a Last.fm account via user.getRecentTracks. */
    public const LASTFM = 'lastfm';

    /** Polled from the Spotify Web API (recently-played). */
    public const SPOTIFY = 'spotify';

    /** Submitted live to Earmark's inbound scrobble API by a third-party client. */
    public const SCROBBLE = 'scrobble';

    /** Added or edited by the user by hand. */
    public const MANUAL = 'manual';

    public const ALL = [
        self::LASTFM,
        self::SPOTIFY,
        self::SCROBBLE,
        self::MANUAL,
    ];

    public static function isValid(string $source): bool
    {
        return in_array($source, self::ALL, true);
    }
}
