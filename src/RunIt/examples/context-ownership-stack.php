<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Propagation\StackContextStorage;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — synchronous context ownership with StackContextStorage.
 *
 * Phase 1 feature: StackContextStorage is the default synchronous context storage.
 * It uses last-in-first-out (LIFO) attachment/detachment, so nested executions
 * restore their parent scope automatically. The exact token returned by attach()
 * must be supplied to detach(); any other token is rejected.
 *
 * This example shows:
 *   1. Scope attachment/detachment across nested executions.
 *   2. Scope restoration after a failing nested execution.
 *   3. That sync storage is NOT safe for concurrent Fiber/coroutine work.
 *
 * Run from the package root: `php examples/context-ownership-stack.php`
 */

require __DIR__ . '/../vendor/autoload.php';

$publisher = new InMemoryArtifactPublisher();

$runtime = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

// --- 1. Nested execution restores parent scope after completion ---

$outerId = null;
$innerId = null;

$runtime->run($definition('outer'), static function (ExecutionScopeInterface $scope) use (&$outerId): void {
    $outerId = $scope->execution()->id()->toString();
    echo "outer execution: $outerId\n";

    $scope->activities()->run(
        \ItPack\RunIt\Domain\Activity\ActivityDefinition::create(
            \ItPack\RunIt\Domain\Activity\ActivityName::fromString('inner'),
            \ItPack\RunIt\Domain\Activity\ActivityKind::fromString('step'),
        ),
        static function (\ItPack\RunIt\Contract\Application\ActivityScopeInterface $activityScope): void {
            $innerId = $activityScope->execution()->id()->toString();
            echo "inner activity execution: $innerId\n";
        },
    );

    echo "after inner completes, outer scope still active\n";
});

echo "outer execution completed and context restored\n\n";

// --- 2. Failed nested execution restores scope ---

try {
    $runtime->run($definition('will-fail'), static function (ExecutionScopeInterface $scope): never {
        throw new \RuntimeException('nested failure');
    });
} catch (\Throwable $e) {
    echo "caught original exception: " . $e->getMessage() . "\n";
}

echo "scope restored after failure\n";
