<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Fiber\FiberActivityContextStorage;
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
 * independent attribution regardless of completion order. Activity context is
 * resolved per-recorder call through fiber-local ActivityContextStorageInterface,
 * not once at construction. Each observation binds to its own activity's verified
 * export ID.
 *
 * This example shows:
 *   1. Two overlapping activities on one execution scope, each in its own Fiber.
 *   2. Each Fiber attaches to the context storage before running its activity.
 *   3. Both activities suspend while open, interleaving their lifetimes.
 *   4. Observations captured per activity retain their own verified relationship.
 *   5. Activity A and B have different verified activity IDs.
 *
 * Run from the package root: `php examples/overlapping-activity-provenance.php`
 */

require __DIR__ . '/../vendor/autoload.php';

$publisher = new InMemoryArtifactPublisher();

$storage = new FiberContextStorage();
$activityStorage = new FiberActivityContextStorage();

$runIt = RunItBuilder::create()
    ->withPublisher($publisher)
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage($storage)
    ->withActivityContextStorage($activityStorage)
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
        $obsA = $a->observations()->record('observation from A', ObservationSeverity::Info);
    });
    $scope->activities()->run($activity2, static function (ActivityScopeInterface $b) use (&$obsB): void {
        $obsB = $b->observations()->record('observation from B', ObservationSeverity::Info);
    });
});

echo 'order 1: A then B (sequential baseline)' . "\n";
echo '  A activity id: ' . ($obsA?->activityId()?->toString() ?? 'none') . "\n";
echo '  B activity id: ' . ($obsB?->activityId()?->toString() ?? 'none') . "\n";

// --- Order 2: overlap — two Fibers, both suspend while open ---
$obsA2 = null;
$obsB2 = null;
$ids = [];

$runIt->run($definition('overlap.order2'), static function (ExecutionScopeInterface $scope) use ($activity1, $activity2, $storage, &$ids, &$obsA2, &$obsB2): void {
    $fibers = [];

    foreach (['A', 'B'] as $name) {
        $activity = $name === 'A' ? $activity1 : $activity2;
        $fibers[$name] = new Fiber(static function () use ($scope, $activity, $storage, $name, &$ids, &$obsA2, &$obsB2): void {
            // Fiber-local storage starts empty; attach this execution scope.
            $token = $storage->attach($scope);
            try {
                $scope->activities()->run($activity, static function (ActivityScopeInterface $a) use ($name, &$ids, &$obsA2, &$obsB2): void {
                    $ids[$name] = $a->activity()->activityId()?->toString();
                    if ($name === 'A') {
                        $obsA2 = $a->observations()->record('observation from A', ObservationSeverity::Info);
                    } else {
                        $obsB2 = $a->observations()->record('observation from B', ObservationSeverity::Info);
                    }

                    Fiber::suspend($name . '.suspended');
                });
            } finally {
                $storage->detach($token);
            }
        });
    }

    $fibers['A']->start(); // starts A, runs to suspend
    $fibers['B']->start(); // runs B while A suspended
    $fibers['A']->resume(); // resume A — completes
    $fibers['B']->resume(); // resume B — completes
});

echo 'order 2: A and B overlap (both suspended, interleaved)' . "\n";
echo '  A activity id: ' . ($ids['A'] ?? 'none') . "\n";
echo '  B activity id: ' . ($ids['B'] ?? 'none') . "\n";
echo '  A observation activity id: ' . ($obsA2?->activityId()?->toString() ?? 'none') . "\n";
echo '  B observation activity id: ' . ($obsB2?->activityId()?->toString() ?? 'none') . "\n";
$activityIdsDiffer = $ids['A'] !== null && $ids['B'] !== null && $ids['A'] !== $ids['B'];
echo '  A and B activity ids differ: ' . ($activityIdsDiffer ? 'yes' : 'no') . "\n";
$observationIdsMatch = $obsA2?->activityId()?->toString() === $ids['A']
    && $obsB2?->activityId()?->toString() === $ids['B'];
echo '  observations retain activity ids: ' . ($observationIdsMatch ? 'yes' : 'no') . "\n";
if (!$activityIdsDiffer || !$observationIdsMatch) {
    throw new RuntimeException('overlapping activities must have distinct verified activity IDs and observations must retain them');
}

echo 'overlapping activity provenance: independent attribution confirmed' . "\n";
