<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\OpenTelemetry\Metrics;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\OpenTelemetry\Metrics\InstrumentCache;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(InstrumentCache::class)]
final class InstrumentCacheTest extends Unit
{
    public function testHasReturnsFalseForEmptyCache(): void
    {
        $cache = new InstrumentCache(3);

        self::assertFalse($cache->has('key-1'));
    }

    public function testHasReturnsTrueAfterPut(): void
    {
        $cache = new InstrumentCache(3);
        $cache->put('key-1', new stdClass());

        self::assertTrue($cache->has('key-1'));
    }

    public function testGetReturnsInstrumentAfterPut(): void
    {
        $cache = new InstrumentCache(3);
        $instrument = new stdClass();
        $cache->put('key-1', $instrument);

        self::assertSame($instrument, $cache->get('key-1'));
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $cache = new InstrumentCache(3);

        self::assertNull($cache->get('missing'));
    }

    public function testPutEvictsOldestWhenFull(): void
    {
        $cache = new InstrumentCache(2);
        $cache->put('key-1', new stdClass());
        $cache->put('key-2', new stdClass());
        $cache->put('key-3', new stdClass());

        self::assertFalse($cache->has('key-1'));
        self::assertTrue($cache->has('key-2'));
        self::assertTrue($cache->has('key-3'));
        self::assertSame(2, $cache->size());
    }

    public function testPutDoesNotEvictWhenNotFull(): void
    {
        $cache = new InstrumentCache(3);
        $cache->put('key-1', new stdClass());
        $cache->put('key-2', new stdClass());

        self::assertTrue($cache->has('key-1'));
        self::assertTrue($cache->has('key-2'));
        self::assertSame(2, $cache->size());
    }

    public function testPutOverwritesExistingKey(): void
    {
        $cache = new InstrumentCache(3);
        $instrument1 = new stdClass();
        $instrument2 = new stdClass();
        $cache->put('key-1', $instrument1);
        $cache->put('key-1', $instrument2);

        self::assertSame($instrument2, $cache->get('key-1'));
        self::assertSame(1, $cache->size());
    }
}
