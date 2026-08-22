<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Example;

use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\FeatureFlagSchema;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\NativeConfigurationSchema;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\FlagEvaluator;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use Nette\Schema\Expect;

/**
 * M7 / E8.2 — feature flags.
 *
 * Flags are declared in configuration (here a YAML file) and validated with the
 * reusable {@see FeatureFlagSchema} fragment, so the resolved snapshot carries
 * *validated* flag definitions. Evaluation happens on top of the resolved config
 * via {@see FlagEvaluator} and an {@see ExecutionContextInterface} — no separate
 * source of truth.
 *
 * Run from the package root:
 *   php examples/feature-flags.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$schema = new NativeConfigurationSchema(
    Expect::structure([
        'feature_flags' => FeatureFlagSchema::structure(),
        'service' => Expect::structure([
            'name' => Expect::string(),
        ]),
    ]),
);

$resolver = (new ConfigurationBuilder())
    ->addReader(new FileReader())
    ->addSource(new FileSource(__DIR__ . '/Support/FeatureFlags'))
    ->addLoaders(ConfigurationLoaders::default())
    ->withSchema($schema)
    ->build();

$snapshot = $resolver->resolve();

echo 'Resolved service.name = ' . $snapshot->get('service.name') . PHP_EOL . PHP_EOL;

// The evaluator reads flag definitions from the resolved snapshot.
$evaluator = new FlagEvaluator($snapshot);

$contexts = [
    'production / anonymous' => new ExecutionContext('production'),
    'staging / anonymous' => new ExecutionContext('staging'),
    'production / tenant=acme' => new ExecutionContext('production', ['tenant' => 'acme']),
    'production / tenant=initech' => new ExecutionContext('production', ['tenant' => 'initech']),
    'production / plan=enterprise' => new ExecutionContext('production', ['plan' => 'enterprise']),
];

foreach ($contexts as $label => $ctx) {
    printf('%-32s => %s' . PHP_EOL, $label, json_encode($evaluator->evaluate($ctx)));
}
