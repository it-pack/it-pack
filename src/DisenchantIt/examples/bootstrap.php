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

use ItPack\DisenchantIt\ConstantNameGenerator;
use ItPack\DisenchantIt\Contract\CandidateFilterInterface;
use ItPack\DisenchantIt\NumericNameGenerator;
use ItPack\DisenchantIt\Contract\DisenchanterInterface;
use ItPack\DisenchantIt\ConstantPlacementStrategyInterface;
use ItPack\DisenchantIt\Disenchanter;
use ItPack\DisenchantIt\ValueObject\SemConvConfigInterface;
use ItPack\DisenchantIt\Factory\ConstantPlacementStrategyFactory;
use ItPack\DisenchantIt\Factory\DetectorFactory;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;
use ItPack\DisenchantIt\LineBasedRewriter;
use ItPack\DisenchantIt\SemConvAliasRewriter;
use ItPack\DisenchantIt\SemConvCollector;
use ItPack\DisenchantIt\ValueObject\SemConvConfig;

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
 * showcase a specific placement strategy, a custom filter, or a configured
 * SemConv registry. Any argument left null falls back to the default.
 */
function buildCustom(
    ?ConstantPlacementStrategyInterface $placement = null,
    ?CandidateFilterInterface $filter = null,
    ?SemConvConfigInterface $semconv = null,
): DisenchanterInterface {
    $detectors = DetectorFactory::create();
    $strategy = $placement ?? ConstantPlacementStrategyFactory::create()->buildAuto();
    $config = $semconv ?? new SemConvConfig();

    return new Disenchanter(
        $detectors->build(),
        $filter ?? $detectors->buildFilter(),
        $detectors->buildGrouper(),
        ConstantNameGenerator::create(),
        NumericNameGenerator::create(),
        $strategy,
        new LineBasedRewriter(),
        new SemConvCollector($config),
        new SemConvAliasRewriter($config),
    );
}
