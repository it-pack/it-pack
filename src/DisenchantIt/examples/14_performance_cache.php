<?php

declare(strict_types=1);

/**
 * Phase P4 benchmark — parse-once caching.
 *
 * Demonstrates, with concrete counts, that the Detector:
 *   1. Builds the (expensive) nikic parser exactly once per Detector and
 *      reuses it across every scan (lazy and reused parser stack), and
 *   2. Parses a given SourceCode only once thanks to the WeakMap
 *      ParsedSourceCache — re-scanning the SAME source is a pure cache hit.
 *
 * Run: php examples/14_performance_cache.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ItPack\DisenchantIt\Contract\ParserFactoryInterface;
use ItPack\DisenchantIt\Adapter\Detector;
use ItPack\DisenchantIt\Factory\MagicStringVisitorFactory;
use ItPack\DisenchantIt\Factory\NikicTraverserFactory;
use ItPack\DisenchantIt\Adapter\ParsedSourceCache;
use ItPack\DisenchantIt\Adapter\RoleClassifier;
use ItPack\DisenchantIt\ValueObject\SourceCode;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Wraps a real parser to count how many times parse() is actually invoked —
 * the metric that proves the WeakMap cache avoids re-parsing the same source.
 */
final class CountingParser implements Parser
{
    public int $parses = 0;

    public function __construct(private readonly Parser $inner)
    {
    }

    public function parse(string $code, ?\PhpParser\ErrorHandler $errorHandler = null): ?array
    {
        $this->parses++;

        return $this->inner->parse($code, $errorHandler);
    }

    public function getTokens(): array
    {
        return $this->inner->getTokens();
    }
}

final class CountingParserFactory implements ParserFactoryInterface
{
    public int $builds = 0;
    public ?CountingParser $last = null;

    public function build(): Parser
    {
        $this->builds++;
        $this->last = new CountingParser(
            new ParserFactory()->createForNewestSupportedVersion(),
        );

        return $this->last;
    }
}

function makeLargeSource(int $classes): string
{
    $parts = ['<?php'];
    for ($i = 0; $i < $classes; $i++) {
        $parts[] = "class C$i {
    public function a(): string { return 'value-$i'; }
    public function b(): int { return $i; }
    public function c(): void { \$mask = 0x$i; }
}";
    }

    return implode("\n", $parts);
}

$iterations = 100;
$source = makeLargeSource(300);

$factory = new CountingParserFactory();
$detector = new Detector(
    new RoleClassifier(),
    $factory,
    new NikicTraverserFactory(),
    new MagicStringVisitorFactory(),
    new ParsedSourceCache(),
);

// --- Same SourceCode scanned many times: should be 1 parse total. ---
$same = new SourceCode($source);
for ($i = 0; $i < $iterations; $i++) {
    $detector->scanSource($same);
}
$sameParserBuilds = $factory->builds;
$sameParses = $factory->last?->parses ?? 0;

// Reset and scan DISTINCT sources: parser reused, but each parse is a miss.
$factory2 = new CountingParserFactory();
$detector2 = new Detector(
    new RoleClassifier(),
    $factory2,
    new NikicTraverserFactory(),
    new MagicStringVisitorFactory(),
    new ParsedSourceCache(),
);
for ($i = 0; $i < $iterations; $i++) {
    $detector2->scanSource(new SourceCode(makeLargeSource(300) . "// $i"));
}
$distinctParserBuilds = $factory2->builds;
$distinctParses = $factory2->last?->parses ?? 0;

echo "Iterations per scenario : $iterations\n";
echo "Source size            : " . strlen($source) . " bytes\n\n";
echo sprintf(
    "SAME source     : parser built %d time(s), parse() called %d time(s)  (cache hits: %d)\n",
    $sameParserBuilds,
    $sameParses,
    $iterations - $sameParses,
);
echo sprintf(
    "DISTINCT sources: parser built %d time(s), parse() called %d time(s)\n",
    $distinctParserBuilds,
    $distinctParses,
);
echo "\nTakeaway: the nikic parser is constructed once and reused; re-scanning\n";
echo "the same SourceCode parses 0 additional times (WeakMap cache).\n";
