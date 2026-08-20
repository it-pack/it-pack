<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ConfigurationDiffer;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;

/**
 * End-to-end example: provenance, diagnostics and graph diffing (Roadmap Phase 6).
 *
 * Two JSON files are resolved together (base + local). The higher-priority
 * "local" file overrides keys from "base"; the resolved snapshot then exposes
 * per-node provenance, the resolution reporter carries diagnostics observations,
 * and ConfigurationDiffer explains the difference between base-only and merged.
 *
 * Run from the package root:
 *   php examples/provenance.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$reporter = new ResolutionReporter();

$resolver = new ConfigurationBuilder()
    ->addReader(new FileReader())
    ->addSource(new FileSource(__DIR__ . '/Support/Provenance'))
    ->addLoaders(ConfigurationLoaders::default())
    ->build($reporter);

$snapshot = $resolver->resolve();

echo '== Resolved values ==' . PHP_EOL;
echo 'app.debug = ' . var_export($snapshot->get('app.debug'), true) . PHP_EOL;
echo 'db.host   = ' . var_export($snapshot->get('db.host'), true) . PHP_EOL;

echo PHP_EOL . '== Provenance (db.host) ==' . PHP_EOL;
$provenance = $snapshot->provenance('db.host');
echo 'resourceUri   = ' . $provenance->resourceUri() . PHP_EOL;
echo 'adapter       = ' . $provenance->adapter() . PHP_EOL;
echo 'overridden    = ' . var_export($provenance->overridden(), true) . PHP_EOL;
echo 'overridden by = ' . var_export($provenance->overriddenSource(), true) . PHP_EOL;

echo PHP_EOL . '== Diagnostics observations ==' . PHP_EOL;
foreach ($reporter->observations() as $observation) {
    echo sprintf('[%s] %s', $observation->level(), $observation->message()) . PHP_EOL;
}

echo PHP_EOL . '== Graph diff: base vs merged ==' . PHP_EOL;
$baseGraph = new ConfigurationGraph([
    new ConfigurationNode('db.host', 'base-db', 'base.json', 10, [], new Provenance('base.json', Format::JSON, 'JsonLoader', 10, 'file')),
    new ConfigurationNode('app.debug', false, 'base.json', 10, [], new Provenance('base.json', Format::JSON, 'JsonLoader', 10, 'file')),
]);
$mergedGraph = new ConfigurationGraph([
    new ConfigurationNode('db.host', 'local-db', 'local.json', 20, [], (new Provenance('local.json', Format::JSON, 'JsonLoader', 20, 'file'))->asOverridden('base.json')),
    new ConfigurationNode('app.debug', true, 'local.json', 20, [], (new Provenance('local.json', Format::JSON, 'JsonLoader', 20, 'file'))->asOverridden('base.json')),
]);

echo (new ConfigurationDiffer())->diff($baseGraph, $mergedGraph)->summary() . PHP_EOL;
