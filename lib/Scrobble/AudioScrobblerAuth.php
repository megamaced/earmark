<?php

declare(strict_types=1);

namespace OCA\Earmark\Scrobble;

/**
 * AudioScrobbler 1.2 handshake auth. The client computes
 * `a = md5(md5(password) + timestamp)` where the "password" is the user's
 * Earmark scrobble token; we store `md5(token)` and recompute the expected
 * value to verify. Pure / framework-free so it can be unit-tested.
 */
final class AudioScrobblerAuth
{
    /** Expected handshake token for a given stored md5(token) and client timestamp. */
    public static function expected(string $tokenMd5, string $timestamp): string
    {
        return md5($tokenMd5 . $timestamp);
    }

    /** Constant-time comparison of the client-supplied auth against the expected value. */
    public static function verify(string $tokenMd5, string $timestamp, string $provided): bool
    {
        return hash_equals(self::expected($tokenMd5, $timestamp), strtolower(trim($provided)));
    }
}
