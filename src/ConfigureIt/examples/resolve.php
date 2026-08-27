<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;

/**
 * End-to-end example: discover config files + environment, parse them through
 * the pipeline and freeze an immutable snapshot.
 *
 * Run from the package root:
 *   php examples/resolve.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$resolver = \ItPack\ConfigureIt\Wiring\Configurations::builder()
    ->addReader(new FileReader())
    ->addSource(new FileSource(__DIR__ . '/Support/Data'))
    ->addLoader(new PhpLoader())
    ->addLoader(new JsonLoader())
    ->build();

$snapshot = $resolver->resolve();

echo 'database.host = ' . var_export($snapshot->get('database.host'), true) . PHP_EOL;
echo 'app.debug     = ' . var_export($snapshot->get('app.debug'), true) . PHP_EOL;
echo 'has app.name  = ' . var_export($snapshot->has('app.name'), true) . PHP_EOL;
