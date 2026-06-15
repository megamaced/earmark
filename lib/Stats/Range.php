<?php

declare(strict_types=1);

namespace OCA\Earmark\Stats;

/**
 * Maps a stats range keyword to a lower-bound Unix timestamp. `all` (and any
 * unknown value) means "no lower bound". Pure / framework-free.
 */
final class Range
{
    // 'year' is kept as an alias of '365d' for the existing Overview UI.
    public const RANGES = ['7d', '30d', '90d', '180d', '365d', 'year', 'all'];

    private const DAY = 86400;

    /** @return int|null lower-bound timestamp, or null for the whole history */
    public static function fromTimestamp(string $range, int $now): ?int
    {
        return match ($range) {
            '7d'           => $now - 7 * self::DAY,
            '30d'          => $now - 30 * self::DAY,
            '90d'          => $now - 90 * self::DAY,
            '180d'         => $now - 180 * self::DAY,
            '365d', 'year' => $now - 365 * self::DAY,
            default        => null,
        };
    }

    /** Normalise an arbitrary input to a known range, defaulting to 'all'. */
    public static function normalize(string $range): string
    {
        return in_array($range, self::RANGES, true) ? $range : 'all';
    }
}
