<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Detection\Detector;
use ItPack\DisenchantIt\Adapter\Detection\ParsedSourceCache;
use ItPack\DisenchantIt\Adapter\Detection\RoleClassifier;
use ItPack\DisenchantIt\Contract\Detection\ParsedSourceCacheInterface;
use ItPack\DisenchantIt\Contract\Detection\StringLiteralDetectorInterface;
use ItPack\DisenchantIt\Exception\InvalidCacheConfigurationException;
use ItPack\DisenchantIt\Exception\SourceTooLargeException;
use ItPack\DisenchantIt\Factory\MagicStringVisitorFactory;
use ItPack\DisenchantIt\Factory\NikicTraverserFactory;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\Source\SourceCode;

final class DetectorCacheTest extends Unit
{
    private CountingParserFactory $parsers;

    private function detector(): Detector
    {
        $this->parsers = new CountingParserFactory();

        return new Detector(
            new RoleClassifier(),
            $this->parsers,
            new NikicTraverserFactory(),
            new MagicStringVisitorFactory(),
            new ParsedSourceCache(),
        );
    }

    public function testParserIsBuiltLazilyOnFirstScanOnly(): void
    {
        $detector = $this->detector();

        $this->assertSame(0, $this->parsers->builds, 'parser not materialized before first scanSource');

        $detector->scanSource(new SourceCode('<?php class C { public function f(): int { return 1; } }'));

        $this->assertSame(1, $this->parsers->builds, 'parser built on first scanSource');
    }

    public function testSameSourceCodeParsedExactlyOnce(): void
    {
        $detector = $this->detector();
        $source = new SourceCode('<?php class C { public function f(): int { return 1; } }');

        $first = $detector->scanSource($source);
        $second = $detector->scanSource($source);

        // Cache hit on the second call: no additional parser construction.
        $this->assertSame(1, $this->parsers->builds, 're-scanning the same SourceCode reuses the cached result');
        $this->assertSame($first, $second, 'cached result is returned by reference-stable identity');
    }

    public function testParserIsReusedAcrossDifferentSources(): void
    {
        $detector = $this->detector();

        $detector->scanSource(new SourceCode('<?php class A { public function f(): int { return 1; } }'));
        $detector->scanSource(new SourceCode('<?php class B { public function f(): int { return 2; } }'));

        // Two distinct sources, two traversals, but the (expensive) parser is
        // built only once and reused.
        $this->assertSame(1, $this->parsers->builds, 'parser is reused across different sources');
    }

    public function testStreamingYieldsEveryLiteral(): void
    {
        $detector = $this->detector();
        $source = new SourceCode(
            '<?php class C { public function a(): string { return "x"; }'
            . ' public function b(): int { return 1; } }',
        );

        $all = $detector->scanSource($source);

        $streamed = iterator_to_array($detector->scanSourceStreaming($source));

        $this->assertCount(count($all), $streamed);
        $this->assertContainsOnlyInstancesOf(LiteralInterface::class, $streamed);
    }

    public function testParsedSourceCacheStoresAndRetrievesPerSource(): void
    {
        $cache = new ParsedSourceCache();
        $source = new SourceCode('<?php class C {}');

        $this->assertFalse($cache->has($source));
        $this->assertNull($cache->get($source));

        $cache->put($source, []);

        $this->assertTrue($cache->has($source));
        $this->assertSame([], $cache->get($source));
    }

    public function testMaxEntriesConstantIsSane(): void
    {
        $this->assertSame(32, ParsedSourceCacheInterface::DEFAULT_MAX_ENTRIES);
    }

    /**
     * F4 regression: the store must be bounded. Inserting more distinct
     * sources than the default cap evicts the oldest entries (FIFO) instead of
     * growing without limit.
     */
    public function testDefaultBoundEvictsOldestWhenExceeded(): void
    {
        $cache = new ParsedSourceCache();
        $sources = [];
        $count = ParsedSourceCacheInterface::DEFAULT_MAX_ENTRIES + 3;

        for ($i = 0; $i < $count; ++$i) {
            $sources[$i] = new SourceCode('<?php class C' . $i . ' {}');
            $cache->put($sources[$i], []);
        }

        foreach ($sources as $i => $source) {
            if ($i < 3) {
                $this->assertFalse($cache->has($source), "entry #{$i} was evicted");
            } else {
                $this->assertTrue($cache->has($source), "entry #{$i} is still held");
            }
        }
    }

    /**
     * F4: eviction is deterministic FIFO and never triggered by re-putting an
     * already-cached key.
     */
    public function testRePutOfExistingKeyDoesNotGrowStoreOrEvict(): void
    {
        $cache = new ParsedSourceCache(maxEntries: 2);
        $a = new SourceCode('<?php class A {}');
        $b = new SourceCode('<?php class B {}');
        $c = new SourceCode('<?php class C {}');

        $cache->put($a, []);
        $cache->put($b, []);
        $cache->put($a, []); // re-put at capacity: must not evict anything

        $this->assertTrue($cache->has($a));
        $this->assertTrue($cache->has($b), 're-put of existing key does not evict');

        $cache->put($c, []); // now at capacity again: oldest (A) is evicted

        $this->assertFalse($cache->has($a), 'FIFO evicts the oldest entry');
        $this->assertTrue($cache->has($b));
        $this->assertTrue($cache->has($c));
    }

    /**
     * F4: a bound below one entry cannot hold even the single source being
     * scanned — configuration error, fail-closed.
     */
    public function testZeroMaxEntriesThrows(): void
    {
        $this->expectException(InvalidCacheConfigurationException::class);
        $this->expectExceptionMessageMatches('/must be at least 1, got 0/');

        new ParsedSourceCache(maxEntries: 0);
    }

    /**
     * F1 regression: the cache must key by content identity, not by object
     * identity. detect → rewrite → rewriteWithSemConv each wrap the source in a
     * fresh SourceCode object, so the parse must still be reused.
     */
    public function testEqualSourceCodeParsedExactlyOnceAcrossDistinctObjects(): void
    {
        $detector = $this->detector();
        $code = '<?php class C { public function f(): int { return 1; } }';

        $first = $detector->scanSource(new SourceCode($code));
        $second = $detector->scanSource(new SourceCode($code));

        // Cache hit on the second call even though it is a different object:
        // no additional parser construction.
        $this->assertSame(
            1,
            $this->parsers->builds,
            're-scanning equal code in a distinct SourceCode reuses the cached result',
        );
        $this->assertSame($first, $second, 'cached result is returned by reference-stable identity');
    }

    /**
     * F8 regression: a source larger than the configured bound is rejected
     * before parsing (no parser construction).
     */
    public function testSourceExceedingBoundThrows(): void
    {
        $parsers = new CountingParserFactory();
        $detector = new Detector(
            new RoleClassifier(),
            $parsers,
            new NikicTraverserFactory(),
            new MagicStringVisitorFactory(),
            new ParsedSourceCache(),
            maxSourceBytes: 10,
        );

        $this->expectException(SourceTooLargeException::class);
        $this->expectExceptionMessageMatches('/exceeding the configured limit of 10 bytes/');

        $detector->scanSource(new SourceCode('<?php class C {}'));
    }

    public function testMaxSourceBytesConstantIsSane(): void
    {
        $this->assertSame(1_000_000, StringLiteralDetectorInterface::MAX_SOURCE_BYTES);
    }
}
