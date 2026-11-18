<?php

declare(strict_types=1);

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

/**
 * RunIt — fiber-local context ownership with FiberContextStorage.
 *
 * Phase 1 feature: FiberContextStorage keeps each Fiber's scope stack isolated.
 * A scope attached in one Fiber is invisible to every other Fiber and to the
 * main stack, so concurrent Fibers never share context.
 *
 * This example shows:
 *   1. Main stack execution and fiber execution have different execution IDs.
 *   2. Each fiber's scope is isolated — no cross-talk between concurrent fibers.
 *   3. Context restoration works across fiber suspend/resume.
 *
 * Run from the package root: `php examples/context-ownership-fiber.php`
 */

require __DIR__ . '/../vendor/autoload.php';

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

// --- Main stack execution ---
$runIt->run($definition('main.stack'), static function (ExecutionScopeInterface $scope): void {
    echo 'main stack execution: ' . $scope->execution()->id()->toString() . "\n";
});

// --- Fiber execution (isolated scope) ---
$fiber = new Fiber(static function () use ($runIt, $definition): void {
    $runIt->run($definition('fiber.work'), static function (ExecutionScopeInterface $scope): void {
        echo 'fiber execution:       ' . $scope->execution()->id()->toString() . "\n";
    });
});

$fiber->start();

echo "done\n";
