# Tutorial 4 — Occurrences & snapshots

**Goal:** record immutable **occurrences** (facts) and **snapshots** (state projections).

Build on [Part 3](03-measure-and-observe.md). Occurrences and snapshots are the two
artifact types meant for *downstream systems*, not just human readers.

## Occurrences (immutable facts)

An occurrence is a versioned fact about something that happened. Its type is
lowercase, stable, and suffixed with a version (`order.placed.v1`). The payload is
recursively primitive. Occurrences map to **CloudEvents**, not to logs.

```php
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;

$runtime->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('order.place'),
        ExecutionKind::fromString('operation'),
    ),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->occurrences()->record(
            OccurrenceType::fromString('order.placed.v1'),
            'order',                         // source / subject
            data: ['order_id' => 42, 'status' => 'paid'],
        );
    },
);
```

> **Run this:** [`examples/occurrences.php`](https://github.com/it-pack/run-it/blob/main/examples/occurrences.php).
> See also [`docs/integrations/cloudevents.md`](../integrations/cloudevents.md).

Because the type is versioned and independent of the PHP class name, consumers can
evolve safely (ADR-011).

## Snapshots (state projections)

A snapshot is an immutable projection of resulting state, bound to a deterministic
content hash. A store can verify integrity and link predecessors without keeping every
payload inline.

```php
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;

$runtime->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('order.place'),
        ExecutionKind::fromString('operation'),
    ),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->snapshots()->record(
            SnapshotType::fromString('order.state'),
            payload: ['order_id' => 42, 'status' => 'paid'],
            classification: SnapshotClassification::Terminal,
        );
    },
);
```

> **Run this:** [`examples/snapshots.php`](https://github.com/it-pack/run-it/blob/main/examples/snapshots.php).
> Deep dive: [`docs/concepts/snapshot.md`](../concepts/snapshot.md).

## What you learned

- Occurrences are versioned facts for event streaming (CloudEvents); observations are notes for humans.
- Snapshots are integrity-checked state projections, not debug dumps.

## Next

[Part 5 — Context & classification](05-context-and-propagation.md): the metadata that
travels with every execution, and how RunIt protects sensitive values.
