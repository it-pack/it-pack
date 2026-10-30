<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Port;

use ItPack\AsyncIt\Contract\Port\PollerVocabulary;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Contract\Port\PollerVocabulary
 */
final class PollerVocabularyTest extends TestCase
{
    public function testNanosecondConstants(): void
    {
        self::assertSame(1_000_000_000, PollerVocabulary::NANOSECONDS_PER_SECOND);
        self::assertSame(1_000_000, PollerVocabulary::NANOSECONDS_PER_MILLISECOND);
        self::assertSame(1_000, PollerVocabulary::NANOSECONDS_PER_MICROSECOND);
    }

    public function testReadableConstant(): void
    {
        self::assertSame(1, PollerVocabulary::READABLE);
    }
}
