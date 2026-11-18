<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use ItPack\RunIt\SDK\RunItInterface;

/**
 * RunIt — context token ownership: id() is non-unique, exact reference rules.
 *
 * Phase 1 feature: ContextTokenInterface::id() returns a storage-defined integer
 * that need not be unique. Only the exact token instance returned by attach()
 * authorizes detach(). This example shows:
 *   1. Two tokens from different storage instances can share the same id().
 *   2. Detach requires exact token reference, not matching id().
 *   3. A foreign token from another storage is rejected.
 *
 * Run from the package root: `php examples/context-token-ownership.php`
 */

require __DIR__ . '/../vendor/autoload.php';

$storageA = new StackContextStorage();
$storageB = new StackContextStorage();

$makeRuntime = (static fn (ContextStorageInterface $storage): RunItInterface => RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage($storage)
    ->build());

$runtimeA = $makeRuntime($storageA);
$runtimeB = $makeRuntime($storageB);

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('token'),
);

// --- Attach in storage A, capture token ---
$tokenA = null;
$runtimeA->run($definition('scope-a'), static function (ExecutionScopeInterface $scope) use (&$tokenA, $storageA): void {
    $tokenA = $storageA->attach($scope);
    echo 'attached scope-a, token id: ' . $tokenA->id() . "\n";
});

// --- Attach in storage B (different instance) ---
$tokenB = null;
$runtimeB->run($definition('scope-b'), static function (ExecutionScopeInterface $scope) use (&$tokenB, $storageB): void {
    $tokenB = $storageB->attach($scope);
    echo 'attached scope-b, token id: ' . $tokenB->id() . "\n";
});

// --- id() may collide across storages; exact reference is what matters ---
echo 'tokenA id: ' . $tokenA->id() . ', tokenB id: ' . $tokenB->id() . "\n";
echo 'ids match: ' . (($tokenA->id() === $tokenB->id()) ? 'yes (coincidental)' : 'no') . "\n";

// --- Detaching storage A's scope with storage B's token is rejected ---
try {
    $storageA->detach($tokenB);
    echo "ERROR: foreign token was accepted (should not happen)\n";
} catch (Throwable $throwable) {
    echo 'foreign token correctly rejected: ' . $throwable->getMessage() . "\n";
}

// --- Detach with the correct token restores the scope ---
$storageA->detach($tokenA);
echo "correct token detached scope-a cleanly\n";
