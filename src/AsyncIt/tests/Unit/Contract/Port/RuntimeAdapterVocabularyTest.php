<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Port;

use ItPack\AsyncIt\Contract\Port\RuntimeAdapterKind;
use ItPack\AsyncIt\Contract\Port\RuntimeAdapterVocabulary;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Contract\Port\RuntimeAdapterVocabulary
 */
final class RuntimeAdapterVocabularyTest extends TestCase
{
    public function testAdapterKindValues(): void
    {
        self::assertSame('reference', RuntimeAdapterVocabulary::ADAPTER_REFERENCE);
        self::assertSame('revolt', RuntimeAdapterVocabulary::ADAPTER_REVOLT);
        self::assertSame('swoole', RuntimeAdapterVocabulary::ADAPTER_SWOOLE);
        self::assertSame('swow', RuntimeAdapterVocabulary::ADAPTER_SWOW);
    }

    public function testEnumCasesMatchVocabulary(): void
    {
        self::assertSame(RuntimeAdapterVocabulary::ADAPTER_REFERENCE, RuntimeAdapterKind::Reference->value);
        self::assertSame(RuntimeAdapterVocabulary::ADAPTER_REVOLT, RuntimeAdapterKind::Revolt->value);
        self::assertSame(RuntimeAdapterVocabulary::ADAPTER_SWOOLE, RuntimeAdapterKind::Swoole->value);
        self::assertSame(RuntimeAdapterVocabulary::ADAPTER_SWOW, RuntimeAdapterKind::Swow->value);
    }
}
