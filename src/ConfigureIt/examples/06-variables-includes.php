<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\CompositeReader;
use ItPack\ConfigureIt\Adapter\ConfigurationLoaders;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ConfigurationLoaderFactory;
use ItPack\ConfigureIt\Core\IncludeProcessor;
use ItPack\ConfigureIt\Core\VariableProcessor;

/**
 * Pipelines create knowledge.
 *
 * Two composable processors transform the graph before it is frozen:
 *  - IncludeProcessor pulls keys from an external file ("@include ...") and
 *    namespaces them under the directive's parent key.
 *  - VariableProcessor resolves "${dotted.key}" placeholders against the graph,
 *    so a value can reference another resolved key.
 *
 * Run from the package root:
 *   php examples/06-variables-includes.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$include = new IncludeProcessor(
    reader: new CompositeReader([new FileReader()]),
    loaders: new ConfigurationLoaderFactory(ConfigurationLoaders::default()),
    baseDirectory: __DIR__ . '/Support/ComposeShared',
);

$resolver = new ConfigurationBuilder()
    ->addReader(new FileReader())
    ->addSource(new FileSource(__DIR__ . '/Support/Compose'))
    ->addLoaders(ConfigurationLoaders::default())
    ->addProcessor($include)
    ->addProcessor(new VariableProcessor())
    ->build();

$snapshot = $resolver->resolve();

echo '== Resolved values ==' . PHP_EOL;
printf('  app.name      = %s' . PHP_EOL, var_export($snapshot->get('app.name'), true));
printf('  db.host       = %s' . PHP_EOL, var_export($snapshot->get('db.host'), true));
printf('  db.dsn        = %s   (interpolated from ${app.name}@${db.host})' . PHP_EOL, var_export($snapshot->get('db.dsn'), true));
printf('  db.password   = %s   (pulled from an @include file)' . PHP_EOL, var_export($snapshot->get('db.password'), true));
printf('  db.port       = %s   (pulled from an @include file)' . PHP_EOL, var_export($snapshot->get('db.port'), true));
printf('  db.secret     = %s   (the @include directive node is dropped)' . PHP_EOL, var_export($snapshot->get('db.secret'), true));
