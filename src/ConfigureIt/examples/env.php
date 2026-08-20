<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use ItPack\ConfigureIt\Adapter\EnvLoader;
use ItPack\ConfigureIt\Adapter\EnvReader;
use ItPack\ConfigureIt\Adapter\EnvSource;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;

/**
 * End-to-end example: discover config files + environment, parse them through
 * the pipeline and freeze an immutable snapshot.
 *
 * Run from the package root:
 *   php examples/resolve.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/Support/Data/', 'app.env');
$dotenv->load();


$resolver = new ConfigurationBuilder()
    ->addReader(new EnvReader())
    ->addSource(new EnvSource())
    ->addLoader(new EnvLoader())
    ->build();

$snapshot = $resolver->resolve();

echo 'app.key = ' . var_export($snapshot->get('APP_KEY'), true) . PHP_EOL;
