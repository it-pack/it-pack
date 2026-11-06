<?php

declare(strict_types=1);

/**
 * RunIt + Fibers — executable example.
 *
 * Builds a runtime that isolates execution context per Fiber and runs operations through a
 * Fiber execution engine, then runs one execution on the main stack and one inside a Fiber to show
 * the scopes stay isolated.
 *
 * Run from the package root: `php examples/fibers.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Fiber\FiberExecutionEngine;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runIt = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage(new FiberContextStorage())
    ->withExecutionEngine(new FiberExecutionEngine())
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('fiber'),
);

$runIt->run($definition('main.stack'), static function (ExecutionScopeInterface $scope): void {
    echo 'main stack execution: ' . $scope->execution()->id()->toString() . PHP_EOL;
});

$fiber = new Fiber(static function () use ($runIt, $definition): void {
    $runIt->run($definition('fiber.work'), static function (ExecutionScopeInterface $scope): void {
        echo 'fiber execution:       ' . $scope->execution()->id()->toString() . PHP_EOL;
    });
});

$fiber->start();

echo 'done' . PHP_EOL;
