<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Fiber\FiberExecutionEngine;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Contract\Application\EngineCapability;
use ItPack\RunIt\Contract\Application\EngineCapabilityInterface;
use ItPack\RunIt\Contract\Application\ExecutionEngineInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — concurrency validation boundary.
 *
 * Phase 1 feature: RunItBuilder::build() rejects a shared StackContextStorage
 * paired with a concurrent engine (engine capability `concurrent: true`),
 * throwing IncompatibleContextStorageException. The opt-in
 * withConcurrencyValidator() seam lets custom consumers add their own
 * validation without changing the storage contract.
 *
 * FiberExecutionEngine reports concurrent: false (each fiber runs
 * sequentially), so it does not trigger the stack rejection.
 *
 * This example shows:
 *   1. Concurrent engine (concurrent: true) + shared stack → rejected.
 *   2. FiberExecutionEngine (concurrent: false) + fiber storage → accepted.
 *   3. Custom validator seam receives capability + storage.
 *
 * Run from the package root: `php examples/concurrency-validation.php`
 */

require __DIR__ . '/../vendor/autoload.php';

// --- 1. Concurrent engine + shared stack → rejected ---
$concurrentEngine = new class implements ExecutionEngineInterface {
    public function run(\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope, callable $operation): mixed
    {
        return $operation($scope);
    }

    public function capability(): EngineCapabilityInterface
    {
        return new EngineCapability(supportsFibers: false, supportsCoroutines: false, concurrent: true);
    }
};

try {
    RunItBuilder::create()
        ->withPublisher(new InMemoryArtifactPublisher())
        ->withClock(new SystemMonotonicClock())
        ->withSequenceAllocator(new InMemorySequenceAllocator())
        ->withSnapshotSerializer(new JsonSnapshotSerializer())
        ->withIdentifierGenerator(new RandomIdentifierGenerator())
        ->withContextStorage(new StackContextStorage())
        ->withExecutionEngine($concurrentEngine)
        ->build()
    ;
    echo "ERROR: build should have rejected shared stack with concurrent engine\n";
} catch (\ItPack\RunIt\Domain\Exception\IncompatibleContextStorageException $e) {
    echo "rejected: " . $e->getMessage() . "\n";
}

// --- 2. FiberExecutionEngine (concurrent: false) + fiber storage → accepted ---
$runIt = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage(new \ItPack\RunIt\Adapter\Fiber\FiberContextStorage())
    ->withExecutionEngine(new FiberExecutionEngine())
    ->build()
;

$runIt->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('concurrent.work'),
        ExecutionKind::fromString('fiber'),
    ),
    static function (ExecutionScopeInterface $scope): void {
        echo "accepted: fiber storage with FiberExecutionEngine, execution " . $scope->execution()->id()->toString() . "\n";
    },
);

// --- 3. Custom validator seam ---
$customValidatorCalled = false;
$customValidator = static function (
    ?EngineCapability $capability,
    ContextStorageInterface $storage,
) use (&$customValidatorCalled): void {
    $customValidatorCalled = true;
    // Custom validation logic here — receives capability + storage.
};

RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage(new StackContextStorage())
    ->withExecutionEngine(new FiberExecutionEngine())
    ->withConcurrencyValidator($customValidator)
    ->build()
;

echo "custom validator invoked: " . ($customValidatorCalled ? 'yes' : 'no') . "\n";
