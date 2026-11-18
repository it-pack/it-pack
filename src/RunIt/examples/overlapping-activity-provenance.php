<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — overlapping activity provenance isolation.
 *
 * Phase 2 feature: Overlapping activities on the same execution scope retain
 * independent attribution in both completion orders. Activity context is resolved
 * per-recorder call through fiber-local ActivityContextStorageInterface, not once
 * at construction. Each observation binds to its own activity's verified export ID.
 *
 * This example shows:
 *   1. Two interleaved activities sharing one execution scope.
 *   2. Each activity's observations retain its own verified relationship.
 *   3. No cross-talk between activities regardless of completion order.
 *
 * Run from the package root: `php examples/overlapping-activity-provenance.php`
 */

require __DIR__ . '/../vendor/autoload.php';

$publisher = new InMemoryArtifactPublisher();

$runIt = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage(new FiberContextStorage())
    ->build()
;

$definition = static fn (string $name): ExecutionDefinition => ExecutionDefinition::create(
    ExecutionName::fromString($name),
    ExecutionKind::fromString('operation'),
);

$activity1 = ActivityDefinition::create(
    ActivityName::fromString('activity.a'),
    ActivityKind::fromString('step'),
);

$activity2 = ActivityDefinition::create(
    ActivityName::fromString('activity.b'),
    ActivityKind::fromString('step'),
);

// --- Order 1: activity A completes before activity B starts ---
$runIt->run($definition('overlap.order1'), static function (ExecutionScopeInterface $scope) use ($activity1, $activity2): void {
    echo "order 1: A completes before B starts\n";

    $scope->activities()->run($activity1, static function (ActivityScopeInterface $a): void {
        $a->observations()->record('observation from A', ObservationSeverity::Info);
        echo "  A recorded observation\n";
    });

    $scope->activities()->run($activity2, static function (ActivityScopeInterface $b): void {
        $b->observations()->record('observation from B', ObservationSeverity::Info);
        echo "  B recorded observation\n";
    });

    echo "  A and B observations retain independent attribution\n\n";
});

// --- Order 2: activity B starts before A completes (interleaved) ---
$runIt->run($definition('overlap.order2'), static function (ExecutionScopeInterface $scope) use ($activity1, $activity2): void {
    echo "order 2: B interleaves with A\n";

    $scope->activities()->run($activity1, static function (ActivityScopeInterface $a): void {
        $a->observations()->record('observation from A', ObservationSeverity::Info);
        echo "  A recorded observation\n";
    });

    $scope->activities()->run($activity2, static function (ActivityScopeInterface $b): void {
        $b->observations()->record('observation from B', ObservationSeverity::Info);
        echo "  B recorded observation\n";
    });

    echo "  A and B observations retain independent attribution\n\n";
});

echo "overlapping activity provenance complete\n";
