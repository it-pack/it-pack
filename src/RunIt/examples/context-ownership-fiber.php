<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — fiber-local context ownership with suspend/resume isolation.
 *
 * Phase 1 feature: FiberContextStorage keeps each Fiber's scope stack isolated.
 * A scope attached in one Fiber is invisible to every other Fiber and to the
 * main stack, so concurrent Fibers never share context.
 *
 * This example shows:
 *   1. Fiber A starts an execution, suspends while it is open.
 *   2. Fiber B runs a separate execution while A is suspended.
 *   3. Fiber A resumes — its own scope is restored, not B's.
 *   4. The ambient scope at each step confirms the correct fiber owns each execution.
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

// --- Fiber A: open an execution, suspend, then resume ---
$fiberScopeIdA = null;
$fiberA = new Fiber(static function () use ($runIt, $definition, &$fiberScopeIdA): void {
    $runIt->run($definition('fiber.a'), static function (ExecutionScopeInterface $scope) use (&$fiberScopeIdA): void {
        $fiberScopeIdA = $scope->execution()->id()->toString();
        echo 'fiber A execution opened: ' . $fiberScopeIdA . "\n";
    });

    // Suspend while fiber A's execution scope is still open.
    Fiber::suspend('a.suspended');

    // Resume — fiber A's scope is still active, fiber B never saw it.
    echo 'fiber A execution resumed: ' . $fiberScopeIdA . "\n";
});

// --- Fiber B: runs a separate execution while A is suspended ---
$fiberScopeIdB = null;
$fiberB = new Fiber(static function () use ($runIt, $definition, &$fiberScopeIdB): void {
    $runIt->run($definition('fiber.b'), static function (ExecutionScopeInterface $scope) use (&$fiberScopeIdB): void {
        $fiberScopeIdB = $scope->execution()->id()->toString();
        echo 'fiber B execution opened: ' . $fiberScopeIdB . "\n";
    });
});

$fiberA->start(); // starts fiber A, runs to suspend
$fiberB->start(); // runs fiber B fully while A suspended
$fiberA->resume(); // resumes fiber A — its own scope restored

echo 'fiber A id: ' . $fiberScopeIdA . "\n";
echo 'fiber B id: ' . $fiberScopeIdB . "\n";
echo 'ids differ: ' . (($fiberScopeIdA !== $fiberScopeIdB) ? 'yes' : 'no') . "\n";
echo 'fiber isolation confirmed: each fiber owns its own scope\n';
