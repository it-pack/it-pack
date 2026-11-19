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
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
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
 *   2. Real Fiber detection (Fiber::getCurrent() !== null).
 *   3. Overlapping execution lifetimes — one fiber suspends while another runs.
 *   4. Scope isolation across runtimes.
 *
 * Run from the package root: `php examples/context-ownership-composite.php`
 */

require __DIR__ . '/../vendor/autoload.php';

final class CompositeDetector implements RuntimeContextDetectorInterface
{
    public function inCoroutine(): bool
    {
        return false;
    }

    public function inFiber(): bool
    {
        return Fiber::getCurrent() !== null;
    }
}

$storage = new CompositeContextStorage(
    new StackContextStorage(),
    new FiberContextStorage(),
    new StackContextStorage(),
    new CompositeDetector(),
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

/**
 * @throws InvalidValueExceptionInterface
 */
$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('composite'),
);

// --- Main stack execution (uses StackContextStorage via detector) ---
$runIt->run($definition('main.stack'), static function (ExecutionScopeInterface $scope): void {
    echo 'main stack execution: ' . $scope->execution()->id()->toString() . "\n";
});

$capturedMainScopeId = null;
$capturedFiberScopeId = null;

// --- Fiber with overlapping lifetimes: suspend, run another, resume ---
$fiber = new Fiber(static function () use ($runIt, $definition, &$capturedFiberScopeId): void {
    $fiberScopeId = null;
    $runIt->run($definition('fiber.work'), static function (ExecutionScopeInterface $scope) use (&$fiberScopeId): void {
        $fiberScopeId = $scope->execution()->id()->toString();
        echo 'fiber execution started: ' . $fiberScopeId . "\n";
    });

    // Suspend: yield control back to main stack while fiber execution is open.
    Fiber::suspend('fiber.suspended');

    // Resume: the fiber execution scope is still the active scope.
    echo 'fiber execution resumed: ' . $fiberScopeId . "\n";
    $capturedFiberScopeId = $fiberScopeId;
});

$result = $fiber->start();
echo 'fiber suspended with result: ' . $result . "\n";

// While fiber is suspended, run a main-stack execution — different scope.
$runIt->run($definition('main.during-fiber'), static function (ExecutionScopeInterface $scope): void {
    echo 'main execution while fiber suspended: ' . $scope->execution()->id()->toString() . "\n";
});

$fiber->resume();

echo "composite context ownership: fiber and main scopes isolated across suspend/resume\n";
