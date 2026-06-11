<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Scrobble\AudioScrobblerAuth;
use PHPUnit\Framework\TestCase;

class AudioScrobblerAuthTest extends TestCase
{
    public function testExpectedMatchesProtocolFormula(): void
    {
        $token     = 'supersecrettoken';
        $tokenMd5  = md5($token);
        $timestamp = '1700000000';

        // What a conforming client computes: md5(md5(password) + timestamp)
        $clientAuth = md5($tokenMd5 . $timestamp);

        self::assertSame($clientAuth, AudioScrobblerAuth::expected($tokenMd5, $timestamp));
        self::assertTrue(AudioScrobblerAuth::verify($tokenMd5, $timestamp, $clientAuth));
    }

    public function testVerifyIsCaseInsensitiveOnProvidedHash(): void
    {
        $tokenMd5  = md5('t');
        $timestamp = '123';
        $auth      = strtoupper(md5($tokenMd5 . $timestamp));

        self::assertTrue(AudioScrobblerAuth::verify($tokenMd5, $timestamp, $auth));
    }

    public function testVerifyRejectsWrongAuth(): void
    {
        $tokenMd5 = md5('t');
        self::assertFalse(AudioScrobblerAuth::verify($tokenMd5, '123', 'deadbeef'));
    }

    public function testVerifyRejectsWrongTimestamp(): void
    {
        $tokenMd5 = md5('t');
        $auth     = md5($tokenMd5 . '123');
        self::assertFalse(AudioScrobblerAuth::verify($tokenMd5, '124', $auth));
    }
}
