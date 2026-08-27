<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\NativeConfigurationSchema;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Exception\ValidationException;
use Nette\Schema\Expect;

/**
 * Schemas define truth.
 *
 * A Nette Schema (behind the ConfigurationSchema port) validates the resolved
 * graph and fails fast with a precise message. ConfigureIt never depends on
 * Nette directly — the adapter translates vendor errors into a domain
 * ValidationException.
 *
 * Run from the package root:
 *   php examples/04-schema-validation.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$schema = new NativeConfigurationSchema(
    Expect::structure([
        'app' => Expect::structure([
            'name' => Expect::string(),
            'debug' => Expect::bool(),
        ]),
        'database' => Expect::structure([
            'host' => Expect::string(),
            'port' => Expect::int(),
        ]),
    ]),
);

echo '== Valid configuration ==' . PHP_EOL;

$valid = \ItPack\ConfigureIt\Wiring\Configurations::builder()
    ->addReader(new FileReader())
    ->addSource(new FileSource(__DIR__ . '/Support/Schema/valid'))
    ->addLoaders(ConfigurationLoaders::default())
    ->withSchema($schema)
    ->build();

$snapshot = $valid->resolve();

printf('  app.name        = %s' . PHP_EOL, var_export($snapshot->get('app.name'), true));
printf('  database.port   = %s' . PHP_EOL, var_export($snapshot->get('database.port'), true));

echo PHP_EOL . '== Invalid configuration (caught) ==' . PHP_EOL;

$invalid = \ItPack\ConfigureIt\Wiring\Configurations::builder()
    ->addReader(new FileReader())
    ->addSource(new FileSource(__DIR__ . '/Support/Schema/invalid'))
    ->addLoaders(ConfigurationLoaders::default())
    ->withSchema($schema)
    ->build();

try {
    $invalid->resolve();
} catch (ValidationException $e) {
    echo '  ' . str_replace("\n", "\n  ", $e->getMessage()) . PHP_EOL;
}
