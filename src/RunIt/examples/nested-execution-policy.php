<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Policy\ActivityOnlyPolicy;
use ItPack\RunIt\Application\Policy\ChildExecutionPolicy;
use ItPack\RunIt\Application\Policy\DetachedExecutionPolicy;
use ItPack\RunIt\Application\Policy\RejectNestedPolicy;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — nested execution policies.
 *
 * Phase 2 feature: NestedExecutionPolicyInterface resolves how nested calls
 * behave when an execution is already active. Modes: child_execution,
 * activity_only, detached_execution, reject_nested.
 *
 * This example shows:
 *   1. ChildExecutionPolicy — nested call becomes a child execution (default).
 *   2. ActivityOnlyPolicy — nested call becomes an activity of the parent.
 *   3. DetachedExecutionPolicy — nested call becomes a correlated root execution.
 *   4. RejectNestedPolicy — nested call is rejected outright.
 *
 * Run from the package root: `php examples/nested-execution-policy.php`
 */

require __DIR__ . '/../vendor/autoload.php';

$makeRuntime = static function (\ItPack\RunIt\Contract\Application\NestedExecutionPolicyInterface $policy): \ItPack\RunIt\SDK\RunItInterface {
    return RunItBuilder::create()
        ->withPublisher(new InMemoryArtifactPublisher())
        ->withClock(new SystemMonotonicClock())
        ->withSequenceAllocator(new InMemorySequenceAllocator())
        ->withSnapshotSerializer(new JsonSnapshotSerializer())
        ->withIdentifierGenerator(new RandomIdentifierGenerator())
        ->withNestedExecutionPolicy($policy)
        ->build()
;
};

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

echo "ChildExecutionPolicy — nested call becomes a child execution\n";
$child = $makeRuntime(new ChildExecutionPolicy());
$child->run($definition('parent'), static function (ExecutionScopeInterface $scope) use ($child, $definition): void {
    $child->run(
        ExecutionDefinition::create(
            ExecutionName::fromString('child'),
            ExecutionKind::fromString('operation'),
        ),
        static function (ExecutionScopeInterface $childScope): void {
            echo "  child execution linked to parent\n";
        },
    );
});

echo "\nActivityOnlyPolicy — nested call becomes an activity\n";
$activity = $makeRuntime(new ActivityOnlyPolicy());
$activity->run($definition('parent'), static function (ExecutionScopeInterface $scope) use ($activity, $definition): void {
    $activity->run(
        ExecutionDefinition::create(
            ExecutionName::fromString('nested.activity'),
            ExecutionKind::fromString('operation'),
        ),
        static function (ExecutionScopeInterface $nestedScope): void {
            echo "  nested call resolved as activity of parent\n";
        },
    );
});

echo "\nDetachedExecutionPolicy — nested call becomes a correlated root\n";
$detached = $makeRuntime(new DetachedExecutionPolicy());
$detached->run($definition('parent'), static function (ExecutionScopeInterface $scope) use ($detached, $definition): void {
    $detached->run(
        ExecutionDefinition::create(
            ExecutionName::fromString('detached'),
            ExecutionKind::fromString('operation'),
        ),
        static function (ExecutionScopeInterface $detachedScope): void {
            echo "  detached root execution correlated with parent\n";
        },
    );
});

echo "\nRejectNestedPolicy — nested call rejected\n";
$reject = $makeRuntime(new RejectNestedPolicy());
try {
    $reject->run($definition('parent'), static function (ExecutionScopeInterface $scope) use ($reject, $definition): void {
        $reject->run(
            ExecutionDefinition::create(
                ExecutionName::fromString('rejected'),
                ExecutionKind::fromString('operation'),
            ),
            static function (ExecutionScopeInterface $rejectedScope): void {
                echo "  should not reach here\n";
            },
        );
    });
} catch (\Throwable $e) {
    echo "  rejected: " . $e->getMessage() . "\n";
}

echo "\nnested execution policies complete\n";
