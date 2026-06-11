<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Db\Listen;
use PHPUnit\Framework\TestCase;

class ListenStatesTest extends TestCase
{
    public function testResolutionStatesAreStable(): void
    {
        self::assertSame(
            ['pending', 'resolved', 'unmatched'],
            Listen::STATES,
        );
    }

    public function testPendingIsTheDefaultStartingState(): void
    {
        self::assertSame('pending', Listen::STATE_PENDING);
        self::assertContains(Listen::STATE_PENDING, Listen::STATES);
    }
}
