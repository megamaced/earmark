<?php

declare(strict_types=1);

namespace OCA\Earmark\Tests\Unit;

use OCA\Earmark\Normalize;
use PHPUnit\Framework\TestCase;

class NormalizeTest extends TestCase
{
    public function testTextLowercasesTrimsAndCollapsesWhitespace(): void
    {
        self::assertSame('the beatles', Normalize::text('  The   Beatles '));
        self::assertSame('a b c', Normalize::text("a\tb\nc"));
        self::assertSame('', Normalize::text('   '));
    }

    public function testContentKeyIsInvariantToCaseAndWhitespace(): void
    {
        $a = Normalize::contentKey('The Beatles', 'Hey Jude', 'Past Masters');
        $b = Normalize::contentKey('  the   beatles ', 'HEY JUDE', 'past masters');
        self::assertSame($a, $b);
    }

    public function testContentKeyDistinguishesDifferentTracks(): void
    {
        $a = Normalize::contentKey('The Beatles', 'Hey Jude', null);
        $b = Normalize::contentKey('The Beatles', 'Let It Be', null);
        self::assertNotSame($a, $b);
    }

    public function testContentKeyTreatsNullAndEmptyAlbumAlike(): void
    {
        self::assertSame(
            Normalize::contentKey('A', 'B', null),
            Normalize::contentKey('A', 'B', ''),
        );
    }

    public function testContentKeyIsSha1Length(): void
    {
        self::assertSame(40, strlen(Normalize::contentKey('A', 'B', 'C')));
    }

    public function testDedupHashIsDeterministic(): void
    {
        $ck = Normalize::contentKey('A', 'B', 'C');
        self::assertSame(
            Normalize::dedupHash($ck, 1_700_000_000),
            Normalize::dedupHash($ck, 1_700_000_000),
        );
    }

    public function testDedupHashVariesWithTimestamp(): void
    {
        $ck = Normalize::contentKey('A', 'B', 'C');
        self::assertNotSame(
            Normalize::dedupHash($ck, 1_700_000_000),
            Normalize::dedupHash($ck, 1_700_000_001),
        );
    }
}
