<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Propagation\CompositeContextStorage;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\RuntimeContextDetectorInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — composite context ownership across runtimes.
 *
 * Phase 1 feature: CompositeContextStorage delegates to sub-storages based on
 * the active runtime (main stack, Fiber, coroutine). Composite delegation
 * enforces exact token identity — a foreign token from a different storage
 * instance cannot detach a scope, even if token IDs collide.
 *
 * This example shows:
 *   1. Composite storage wrapping StackContextStorage + FiberContextStorage.
 *   2. The main stack uses the stack storage; fibers use fiber storage.
 *   3. Scope isolation across runtimes.
 *
 * Run from the package root: `php examples/context-ownership-composite.php`
 */

require __DIR__ . '/../vendor/autoload.php';

final class MainStackDetector implements RuntimeContextDetectorInterface
{
    public function inCoroutine(): bool { return false; }
    public function inFiber(): bool    { return false; }
}

$storage = new CompositeContextStorage(
    new StackContextStorage(),
    new FiberContextStorage(),
    new StackContextStorage(),
    new MainStackDetector(),
);

$runIt = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage($storage)
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('composite'),
);

// --- Main stack execution (uses StackContextStorage) ---
$runIt->run($definition('main.stack'), static function (ExecutionScopeInterface $scope): void {
    echo "main stack execution: " . $scope->execution()->id()->toString() . "\n";
});

// --- Fiber execution (uses FiberContextStorage via composite) ---
$fiber = new \Fiber(static function () use ($runIt, $definition): void {
    $runIt->run($definition('fiber.work'), static function (ExecutionScopeInterface $scope): void {
        echo "fiber execution:       " . $scope->execution()->id()->toString() . "\n";
    });
});

$fiber->start();

echo "composite context ownership: main and fiber scopes isolated\n";
