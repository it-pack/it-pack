<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use ItPack\ConfigureIt\Adapter\CompositeReader;
use ItPack\ConfigureIt\Adapter\EnvLoader;
use ItPack\ConfigureIt\Adapter\EnvReader;
use ItPack\ConfigureIt\Adapter\EnvSource;
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
 *   php examples/all.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/Support/Data/', 'app.env');
$dotenv->load();

$resolver = \ItPack\ConfigureIt\Wiring\Configurations::builder()
    ->addReader(new CompositeReader([
        new EnvReader(),
        new FileReader(),
    ]))
    ->addSource(new FileSource(__DIR__ . '/Support/Data'))
    ->addSource(new EnvSource())
    ->addLoader(new PhpLoader())
    ->addLoader(new JsonLoader())
    ->addLoader(new EnvLoader())
    ->build();

$snapshot = $resolver->resolve();

echo 'database.host = ' . var_export($snapshot->get('database.host'), true) . PHP_EOL;
echo 'app.debug     = ' . var_export($snapshot->get('app.debug'), true) . PHP_EOL;
echo 'has app.name  = ' . var_export($snapshot->has('app.name'), true) . PHP_EOL;
