<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\DotenvLoader;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Cache\FileSystemCache;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;

/**
 * M3 — Epic E7.1 (Compiled / Cached Artifacts) example.
 *
 * The first boot resolves normally and writes a frozen, opcache-friendly PHP
 * artifact into the cache directory. Every subsequent boot with identical
 * inputs is served straight from the artifact — no re-discovery, no re-parse,
 * no re-validation — until a source or the schema/context changes.
 *
 * Run from the package root:
 *   php examples/cached.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$cacheDir = sys_get_temp_dir() . '/configure-it-example-cache';

$resolver = \ItPack\ConfigureIt\Wiring\Configurations::builder()
    ->addReader(new FileReader())
    ->addSource(new FileSource(__DIR__ . '/Support/Data'))
    ->addLoader(new PhpLoader())
    ->addLoader(new JsonLoader())
    ->addLoader(new DotenvLoader())
    ->withCache(new FileSystemCache($cacheDir))
    ->build();

$snapshot = $resolver->resolve();

echo 'database.host = ' . var_export($snapshot->get('database.host'), true) . PHP_EOL;
echo 'app.debug     = ' . var_export($snapshot->get('app.debug'), true) . PHP_EOL;
echo 'served from cache directory: ' . $cacheDir . PHP_EOL;
