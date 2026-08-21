# Snapshot

A **snapshot** is an immutable projection of state produced by or during an
execution. It binds a normalized payload to a deterministic content hash so a
store can detect tampering and resolve predecessor chains. The concept is defined
by [`runit:Snapshot`](https://w3id.org/itpack/runit#Snapshot).

> Snapshots preserve work.

## What a snapshot represents

A snapshot answers "what is the resulting state?" — a frozen, serializable view of
configuration, an execution result, a pipeline state, or a workflow checkpoint.

Examples:

```
Configuration snapshot    Execution snapshot     Pipeline snapshot
Workflow snapshot         Terminal checkpoint
```

## Types

| Contract | Value object | Notes |
|---|---|---|
| `SnapshotInterface` | `ItPack\RunIt\Domain\Snapshot\Snapshot` (base) | Immutable artifact base |
| `ExecutionSnapshotInterface` | `ItPack\RunIt\Domain\Snapshot\ExecutionSnapshot` | Concrete, final, interface-backed snapshot |
| `SnapshotIdInterface` | `ItPack\RunIt\Domain\Snapshot\SnapshotId` | Opaque stable identifier |
| `SnapshotTypeInterface` | `ItPack\RunIt\Domain\Snapshot\SnapshotType` | Validated, extensible type |
| `SnapshotClassificationInterface` | `ItPack\RunIt\Domain\Snapshot\SnapshotClassification` | Closed classification enum |

`SnapshotClassification` is the closed set `checkpoint`, `terminal`. A terminal
snapshot represents a final execution state; a checkpoint is an intermediate state.

`Snapshot` is intentionally **not** `final` so consumers can refine it with
interface-backed subclasses (e.g. `ExecutionSnapshot`); concrete snapshots such as
`ExecutionSnapshot` are `final` and implement a dedicated interface (ADR-015).

## Ownership

- Every snapshot belongs to exactly one execution (`executionId()`).
- A snapshot may be bound to an activity (`activityId()`), or be execution-level
  (`null`).

## Normalization

`Snapshot::normalize()` returns a deterministic, recursively-primitive array:

```text
id                string   snapshot identifier
execution_id      string   owning execution identifier
activity_id       string|null
type              string   snapshot type
classification    string   checkpoint | terminal
schema_version    int      payload schema version
created_at        int      Unix epoch seconds
content_type      string   payload content type
content_hash      string   deterministic hash of the payload
predecessor_id    string|null
sequence          int      monotonic sequence within the execution
payload           mixed    recursively-primitive payload
attributes        array    bounded attribute map
```

## Hashing and integrity

The `content_hash` is computed by `HashCalculatorInterface`
(`DeterministicHashCalculator`) over the canonical JSON encoding of the payload. A
store can therefore verify integrity and link a snapshot to its predecessor without
retaining every payload inline.

## Invariants

- Snapshots are immutable.
- Snapshot serialization is explicit and deterministic.
- Arbitrary PHP object serialization is prohibited; payloads are recursively
  primitive.
- Snapshot persistence is delegated to a port (`SnapshotStoreInterface`,
  `SnapshotSignerInterface`).
- Payloads and attributes are bounded.

## Public API usage

```php
declare(strict_types=1);

use ItPack\RunIt\Domain\Snapshot\ExecutionSnapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;

$snapshot = ExecutionSnapshot::capture(
    $executionId,
    SnapshotType::fromString('config.resolved'),
    payload: ['entries' => 42],
    schemaVersion: 1,
    classification: SnapshotClassification::Terminal,
);
```

Consumers normally produce snapshots through `ExecutionSnapshotFactoryInterface`
and persist them through the snapshot store port.

## Related decisions

- ADR-002 — Domain artifacts are immutable.
- ADR-015 — Final, interface-backed classes (base `Snapshot` is the exception).
- ADR-018 — Extensible string names and kinds.

See also: [`execution.md`](execution.md), [`context.md`](context.md).
