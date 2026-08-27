<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\DotenvLoader;
use ItPack\ConfigureIt\Adapter\DotenvSource;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;

/**
 * End-to-end example: load a ".env" file through the Dotenv adapter and freeze
 * an immutable snapshot.
 *
 * Run from the package root:
 *   php examples/dotenv.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$resolver = \ItPack\ConfigureIt\Wiring\Configurations::builder()
    ->addReader(new FileReader())
    ->addSource(new DotenvSource(__DIR__ . '/Support/Env/app.env'))
    ->addLoader(new DotenvLoader())
    ->build();

$snapshot = $resolver->resolve();

echo 'app.name  = ' . var_export($snapshot->get('APP_NAME'), true) . PHP_EOL;
echo 'app.debug = ' . var_export($snapshot->get('APP_DEBUG'), true) . PHP_EOL;
echo 'greeting  = ' . var_export($snapshot->get('GREETING'), true) . PHP_EOL;
echo 'nested    = ' . var_export($snapshot->get('NESTED'), true) . PHP_EOL;
