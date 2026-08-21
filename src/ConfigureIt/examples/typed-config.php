<?php

declare(strict_types=1);

/*
 * Typed DTO consumption via codegen (Additional Features Roadmap — M1, E2.3).
 *
 * Run from the package root:
 *
 *     php examples/typed-config.php
 *
 * Generates a strongly-typed, readonly `AppConfig` DTO from a Nette Schema
 * definition, hydrates it from a resolved snapshot in a single call, and prints
 * the result. The generated code depends only on
 * `ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface`.
 */

use ItPack\ConfigureIt\Codegen\ConfigCodegen;
use ItPack\ConfigureIt\Codegen\ConfigDtoHydrator;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\ConfigurationSnapshot;
use Nette\Schema\Expect;

require __DIR__ . '/../vendor/autoload.php';

$schema = Expect::structure([
    'app' => Expect::structure([
        'name' => Expect::string(),
        'port' => Expect::int(),
        'debug' => Expect::bool(),
        'tags' => Expect::listOf(Expect::string()),
    ]),
    'db' => Expect::structure([
        'host' => Expect::string(),
        'pool' => Expect::listOf(Expect::int()),
    ]),
]);

$graph = new ConfigurationGraph([
    new ConfigurationNode('app.name', 'checkout', 'base', 10),
    new ConfigurationNode('app.port', 8080, 'base', 10),
    new ConfigurationNode('app.debug', true, 'base', 10),
    new ConfigurationNode('app.tags', ['a', 'b'], 'base', 10),
    new ConfigurationNode('db.host', 'localhost', 'base', 10),
    new ConfigurationNode('db.pool', [5432, 5433], 'base', 10),
]);

$snapshot = ConfigurationSnapshot::fromGraph($graph);

$namespace = 'ItPack\\ConfigureIt\\Example\\Generated';
$code = (new ConfigCodegen())->generate($schema, 'AppConfig', $namespace);

$file = sys_get_temp_dir() . '/configure-it-typed-config-dto.php';
file_put_contents($file, $code);
require $file;

/** @var object $config */
$config = (new ConfigDtoHydrator())->hydrate($namespace . '\\AppConfig', $snapshot);

echo "Generated DTO (class AppConfig) hydrated from snapshot:\n";
echo json_encode($config->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";

echo "\nTyped getters:\n";
echo "  app.name = ", $config->app->name, "\n";
echo "  app.port = ", var_export($config->app->port, true), "\n";
echo "  app.debug = ", var_export($config->app->debug, true), "\n";
echo "  db.pool = ", json_encode($config->db->pool), "\n";
