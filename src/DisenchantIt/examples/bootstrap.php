<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the DisenchantIt examples.
 *
 * Loads the Composer autoloader (from the package root) and exposes a couple
 * of tiny helpers plus two factory shortcuts so each example stays focused on
 * the feature it demonstrates instead of boilerplate.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ItPack\DisenchantIt\Adapter\Grouping\ClosedSetDetector;
use ItPack\DisenchantIt\Adapter\Naming\ConstantNameGenerator;
use ItPack\DisenchantIt\Adapter\Naming\ConstantNameResolver;
use ItPack\DisenchantIt\Contract\Grouping\CandidateFilterInterface;
use ItPack\DisenchantIt\Adapter\Naming\NumericNameGenerator;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\Contract\Rewriting\PromotionTargetInterface;
use ItPack\DisenchantIt\Core\Disenchanter;
use ItPack\DisenchantIt\Factory\DetectorFactory;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;
use ItPack\DisenchantIt\Factory\PromotionTargetFactory;
use ItPack\DisenchantIt\Adapter\Rewriting\LineBasedRewriter;
use ItPack\DisenchantIt\Adapter\Reporting\NullExecutionRecorder;
use ItPack\DisenchantIt\Adapter\SemConv\SemConvAliasRewriter;
use ItPack\DisenchantIt\Adapter\SemConv\SemConvCollector;
use ItPack\DisenchantIt\ValueObject\SemConv\SemConvConfig;
use ItPack\DisenchantIt\ValueObject\SemConv\SemConvConfigInterface;

function hr(string $title): void
{
    echo str_repeat('═', 72) . PHP_EOL;
    echo '  ' . $title . PHP_EOL;
    echo str_repeat('═', 72) . PHP_EOL . PHP_EOL;
}

function code(string $label, string $source): void
{
    echo $label . ':' . PHP_EOL;
    echo '┌' . str_repeat('─', 70) . '┐' . PHP_EOL;
    echo '│ ' . str_replace("\n", "\n│ ", rtrim($source, "\n")) . PHP_EOL;
    echo '└' . str_repeat('─', 70) . '┘' . PHP_EOL . PHP_EOL;
}

function note(string $text): void
{
    echo 'ℹ️  ' . $text . PHP_EOL . PHP_EOL;
}

/**
 * Build the default orchestrator (Auto placement, default SemConv registry).
 */
function build(): DisenchanterInterface
{
    return DisenchanterFactory::create()->build();
}

/**
 * Build an orchestrator with custom collaborators, used by the examples that
 * showcase a specific promotion target, a custom filter, or a configured SemConv
 * registry. Any argument left null falls back to the default.
 */
function buildCustom(
    ?PromotionTargetInterface $promotion = null,
    ?CandidateFilterInterface $filter = null,
    ?SemConvConfigInterface $semconv = null,
): DisenchanterInterface {
    $detectors = DetectorFactory::create();
    $target = $promotion ?? PromotionTargetFactory::create()->build();
    $config = $semconv ?? new SemConvConfig();

    return new Disenchanter(
        $detectors->build(),
        $filter ?? $detectors->buildFilter(),
        $detectors->buildGrouper(),
        new ConstantNameResolver(
            ConstantNameGenerator::create(),
            NumericNameGenerator::create(),
        ),
        $target,
        new LineBasedRewriter(),
        new SemConvCollector($config),
        new SemConvAliasRewriter($config),
        new NullExecutionRecorder(),
        new ClosedSetDetector(),
    );
}
