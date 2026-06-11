<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Source;
use PHPUnit\Framework\TestCase;

/**
 * Sanity tests on the central listen-source constants. Cheap to run and
 * catches accidental regressions when adding / removing ingest sources.
 */
class SourceTest extends TestCase
{
    public function testAllSourcesAreListed(): void
    {
        self::assertSame(
            ['lastfm', 'spotify', 'scrobble', 'manual'],
            Source::ALL,
        );
    }

    public function testIsValidAcceptsKnownValues(): void
    {
        foreach (Source::ALL as $source) {
            self::assertTrue(Source::isValid($source), $source);
        }
    }

    public function testIsValidRejectsUnknown(): void
    {
        self::assertFalse(Source::isValid(''));
        self::assertFalse(Source::isValid('itunes'));
        self::assertFalse(Source::isValid('LASTFM')); // case-sensitive
        self::assertFalse(Source::isValid(' lastfm'));
    }
}
