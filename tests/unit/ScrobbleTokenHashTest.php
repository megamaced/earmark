<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Service\ScrobbleTokenService;
use PHPUnit\Framework\TestCase;

class ScrobbleTokenHashTest extends TestCase
{
    public function testHashIsSha256HexLength(): void
    {
        self::assertSame(64, strlen(ScrobbleTokenService::hashToken('whatever')));
    }

    public function testHashIsDeterministic(): void
    {
        self::assertSame(
            ScrobbleTokenService::hashToken('abc123'),
            ScrobbleTokenService::hashToken('abc123'),
        );
    }

    public function testHashDiffersPerToken(): void
    {
        self::assertNotSame(
            ScrobbleTokenService::hashToken('token-a'),
            ScrobbleTokenService::hashToken('token-b'),
        );
    }

    public function testHashDoesNotEqualPlaintext(): void
    {
        self::assertNotSame('secret', ScrobbleTokenService::hashToken('secret'));
    }
}
