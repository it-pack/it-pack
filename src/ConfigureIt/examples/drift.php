<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Example;

require_once __DIR__ . '/../vendor/autoload.php';

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Core\ConfigurationDiffer;
use ItPack\ConfigureIt\Core\ConfigurationDriftDetector;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Execution\Occurrence;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureIt\ValueObject\SourceKind;

// In-memory configuration store standing in for a live source.
$store = ['feature.enabled' => false, 'rate' => 10];

$node = static function (string $key, mixed $value) use (&$store): ConfigurationNode {
    return new ConfigurationNode(
        $key,
        $value,
        'store',
        10,
        [],
        new Provenance('store', Format::JSON, 'ArrayLoader', 10, SourceKind::Memory),
    );
};

// The live provider re-reads the current store on every check.
$liveProvider = static function () use (&$store, $node): ConfigurationGraphInterface {
    return new ConfigurationGraph([
        $node('feature.enabled', $store['feature.enabled']),
        $node('rate', $store['rate']),
    ]);
};

$reporter = new ResolutionReporter();

$detector = new ConfigurationDriftDetector(
    liveProvider: $liveProvider,
    reporter: $reporter,
    differ: new ConfigurationDiffer(),
);

// Pin the baseline. With no baseline supplied, the first check pins the live
// graph; subsequent checks only report divergence from it.
echo 'Pinned baseline from live graph.', PHP_EOL;
$detector->check();

// --- Simulate an external drift of the live configuration. ---
$store['feature.enabled'] = true;
$store['rate'] = 50;

$detector->check();

echo 'Drift occurrences: ';
echo implode(', ', array_map(static fn (Occurrence $o): string => $o->type(), $reporter->occurrences())), PHP_EOL;

foreach ($reporter->observations() as $observation) {
    echo ' - [' . $observation->level() . '] ' . $observation->message(), PHP_EOL;
}
