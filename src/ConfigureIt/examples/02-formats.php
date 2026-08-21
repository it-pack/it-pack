<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;

/**
 * Formats are just adapters.
 *
 * The same logical configuration is sharded across PHP, JSON, YAML, TOML, INI,
 * XML and Markdown-Front-Matter files. One API discovers, parses, and merges
 * them into a single immutable snapshot — no format-specific code anywhere.
 *
 * Run from the package root:
 *   php examples/02-formats.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$resolver = new ConfigurationBuilder()
    ->addReader(new FileReader())
    ->addSource(new FileSource(__DIR__ . '/Support/Formats'))
    ->addLoaders(ConfigurationLoaders::default())
    ->build();

$snapshot = $resolver->resolve();

echo '== Merged snapshot: one API, seven formats ==' . PHP_EOL;

$keys = [
    'framework.name',
    'database.host',
    'cache.driver',
    'queue.broker',
    'logging.level',
    'api.baseUrl',
    'site.title',
];

foreach ($keys as $key) {
    printf('  %-18s = %s' . PHP_EOL, $key, var_export($snapshot->get($key), true));
}
