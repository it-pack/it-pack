<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Detector;
use ItPack\DisenchantIt\Exception\SourceTooLargeException;
use ItPack\DisenchantIt\Factory\MagicStringVisitorFactory;
use ItPack\DisenchantIt\Factory\NikicTraverserFactory;
use ItPack\DisenchantIt\ParsedSourceCache;
use ItPack\DisenchantIt\RoleClassifier;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\SourceCode;

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
        $this->assertSame(1, $this->parsers->builds, 're-scanning equal code in a distinct SourceCode reuses the cached result');
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
        $this->assertSame(1_000_000, Detector::MAX_SOURCE_BYTES);
    }
}
