# Tutorial 10 — Activity provenance & overlap

**Goal:** understand how overlapping activities retain independent attribution on
the same execution scope, and why verified handles — not legacy activity IDs —
establish trust.

## The problem

Two activities sharing one execution scope can interleave. Without per-recorder
context resolution, activity A's observation could acquire activity B's verified
relationship, or activity B could lose its activity relationship after A completes.

RunIt solves this by resolving activity context **per-recorder call** through
fiber-local `ActivityContextStorageInterface`, not once at scope construction.
Each recorder re-resolves the owning activity on every call.

## Overlapping activities

```php
use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Fiber\FiberContextStorage;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\SDK\Builder\RunItBuilder;

$runtime = RunItBuilder::create()
    ->withPublisher(new InMemoryArtifactPublisher())
    ->withClock(new SystemMonotonicClock())
    ->withSequenceAllocator(new InMemorySequenceAllocator())
    ->withSnapshotSerializer(new JsonSnapshotSerializer())
    ->withIdentifierGenerator(new RandomIdentifierGenerator())
    ->withContextStorage(new FiberContextStorage())
    ->build()
;

$activityA = ActivityDefinition::create(
    ActivityName::fromString('activity.a'),
    ActivityKind::fromString('step'),
);
$activityB = ActivityDefinition::create(
    ActivityName::fromString('activity.b'),
    ActivityKind::fromString('step'),
);

$runtime->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('overlap'),
        ExecutionKind::fromString('operation'),
    ),
    static function ($scope) use ($activityA, $activityB): void {
        $scope->activities()->run($activityA, static function ($a): void {
            $a->observations()->record('from A', ObservationSeverity::Info);
        });
        $scope->activities()->run($activityB, static function ($b): void {
            $b->observations()->record('from B', ObservationSeverity::Info);
        });
    },
);
```

Both observations retain their own activity's verified relationship, regardless of
completion order.

> **Run this:** `examples/overlapping-activity-provenance.php`

## Verified handles, not legacy IDs

`ContextTokenInterface::id()` is storage-defined and non-unique. Trusted activity
relationships require verified handles bound to the exact owning execution scope —
legacy activity IDs alone are untrusted claims.

## What you learned

- Activity context is resolved per-recorder call, not once at construction.
- Overlapping activities retain independent attribution in both completion orders.
- Verified handles, not `id()`, establish activity relationships.

## Next

[Tutorial 11 — Publication security & field classification](11-publication-security-field-classification.md):
how fields are classified before sampling and the sink.
