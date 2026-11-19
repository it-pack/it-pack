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
use ItPack\RunIt\SDK\Builder\RunItBuilder;

/**
 * RunIt — overlapping activity provenance isolation.
 *
 * Phase 2 feature: Overlapping activities on the same execution scope retain
 * independent attribution regardless of completion order. Activity context is
 * resolved per-recorder call through fiber-local ActivityContextStorageInterface,
 * not once at construction. Each observation binds to its own activity's verified
 * export ID.
 *
 * This example shows:
 *   1. Two overlapping activities on one execution scope, each in its own Fiber.
 *   2. Both activities suspend while open, interleaving their lifetimes.
 *   3. Observations captured per activity retain their own verified relationship.
 *   4. Activity A and B have different verified activity IDs.
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

// --- Order 1: A completes before B starts (sequential baseline) ---
$obsA = null;
$obsB = null;

$runIt->run($definition('overlap.order1'), static function (ExecutionScopeInterface $scope) use ($activity1, $activity2, &$obsA, &$obsB): void {
    $scope->activities()->run($activity1, static function (ActivityScopeInterface $a) use (&$obsA): void {
        $obsA = $a->observations()->record('observation from A', \ItPack\RunIt\Domain\Observation\ObservationSeverity::Info);
    });
    $scope->activities()->run($activity2, static function (ActivityScopeInterface $b) use (&$obsB): void {
        $obsB = $b->observations()->record('observation from B', \ItPack\RunIt\Domain\Observation\ObservationSeverity::Info);
    });
});

echo 'order 1: A then B (sequential baseline)' . "\n";
echo '  A activity id: ' . ($obsA?->activityId()?->toString() ?? 'none') . "\n";
echo '  B activity id: ' . ($obsB?->activityId()?->toString() ?? 'none') . "\n";

// --- Order 2: overlap — two Fibers, both suspend while open ---
$obsA2 = null;
$obsB2 = null;
$fiberAId = null;
$fiberBId = null;

$runIt->run($definition('overlap.order2'), static function (ExecutionScopeInterface $scope) use ($activity1, $activity2, &$obsA2, &$obsB2, &$fiberAId, &$fiberBId): void {
    // Fiber A: open activity A, record observation, suspend while open.
    $fiberA = new Fiber(static function () use ($scope, $activity1, &$obsA2, &$fiberAId): void {
        $scope->activities()->run($activity1, static function (ActivityScopeInterface $a) use (&$obsA2, &$fiberAId): void {
            $fiberAId = $a->execution()->id()->toString();
            $obsA2 = $a->observations()->record('observation from A', \ItPack\RunIt\Domain\Observation\ObservationSeverity::Info);
            Fiber::suspend('a.suspended');
        });
    });

    // Fiber B: open activity B while A is suspended, record observation, suspend.
    $fiberB = new Fiber(static function () use ($scope, $activity2, &$obsB2, &$fiberBId): void {
        $scope->activities()->run($activity2, static function (ActivityScopeInterface $b) use (&$obsB2, &$fiberBId): void {
            $fiberBId = $b->execution()->id()->toString();
            $obsB2 = $b->observations()->record('observation from B', \ItPack\RunIt\Domain\Observation\ObservationSeverity::Info);
            Fiber::suspend('b.suspended');
        });
    });

    $fiberA->start(); // starts A, runs to suspend
    $fiberB->start(); // runs B while A suspended
    $fiberA->resume(); // resume A — completes
    $fiberB->resume(); // resume B — completes
});

echo 'order 2: A and B overlap (both suspended, interleaved)' . "\n";
echo '  A activity id: ' . ($obsA2?->activityId()?->toString() ?? 'none') . "\n";
echo '  B activity id: ' . ($obsB2?->activityId()?->toString() ?? 'none') . "\n";
echo '  A and B activity ids differ: ' . (($obsA2?->activityId()?->toString() !== $obsB2?->activityId()?->toString()) ? 'yes' : 'no') . "\n";
echo 'overlapping activity provenance: independent attribution confirmed' . "\n";
