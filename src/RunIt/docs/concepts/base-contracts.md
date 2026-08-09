# Base Contracts

The base contracts (milestone M1.1) are the shared, dependency-free vocabulary
all RunIt domain artifacts are built from. They live under
`src/Contract/Domain/` and depend on nothing but the PHP language and
stdlib — no framework, adapter, or external standard.

Every public contract interface is marked `@api` and references the canonical
RunIt vocabulary in `vocab/run-it.ttl`.

## Contracts

### NormalizableInterface

Any value that can produce a deterministic, recursively-primitive projection of
itself implements `NormalizableInterface::normalize()`. Normalization is the
RunIt-native way to move a value across process or service boundaries without
arbitrary object serialization. It is:

- deterministic: same object, same output;
- bounded: no secret materialization, no unbounded payloads, no arbitrary
  objects.

### StringProviderInterface and StringValueInterface

Names, kinds, measurement names, occurrence types, and snapshot types all
project to a single canonical string through `StringProviderInterface` and add
type-aware `equals()` through `StringValueInterface`. They are validated string
values, not identities: see [names and kinds](names-and-kinds.md). The shared
primitives `StringValueValidator`, `InvalidStringValueException`, and the
`ValidatedStringValueBehavior` / `StringProviderBehavior` traits underpin every
concrete value object.

### StringIdentifierInterface

Identifiers are stable opaque strings. `StringIdentifierInterface` guarantees a
canonical `toString()` and value-based `equals()`. Concrete identifiers extend
this base and are implemented in milestone M1.2 (see
[identifiers](identifiers.md)):

- `ExecutionIdInterface` / `ExecutionId`
- `ActivityIdInterface` / `ActivityId`
- `ObservationIdInterface` / `ObservationId`
- `OccurrenceIdInterface` / `OccurrenceId`
- `SnapshotIdInterface` / `SnapshotId`
- `CorrelationIdInterface` / `CorrelationId`

All six value objects are `final`, `readonly`, validate their value on
construction through `StringIdentifierValidator`, and compare by type and value.

### ExecutionArtifactInterface

The root marker for every semantic artifact. Executions, activities,
measurements, observations, occurrences, and snapshots all implement it, and a
reader can rely on any artifact being `Normalizable`.

### Ownership contracts

- `ExecutionOwnedInterface::executionId()`: every artifact belongs to exactly
  one execution (identity, not an in-memory collection).
- `ActivityOwnedInterface::activityId()`: activity-bound artifacts report their
  activity; execution-level artifacts return `null`.

Both currently return the base `StringIdentifierInterface`; later milestones
specialize them (for example `ExecutionIdInterface`).

### Timing, sequencing, attributes

- `TimestampedInterface::timestamp()`: point-in-time projection (Unix epoch
  seconds). M1.4 introduces an immutable instant value object.
- `SequencedInterface::sequence()`: monotonic sequence within an execution. M1.4
  introduces an `ArtifactSequence` value object.
- `AttributedInterface::attributes()`: a bounded, recursively-primitive
  attribute map. M1.5 introduces a validated immutable `AttributeCollection`.

These early projections are intentionally primitive so the base layer stays
framework-free. Later milestones widen the contracts with dedicated value
objects before the stable `1.0.0` release.

## Exception markers

`src/Contract/Exception/` defines category marker interfaces that exceptions
implement alongside their SPL base class:

- `RunItExceptionInterface` — root marker for all public RunIt exceptions.
- `InvalidValueExceptionInterface` — an invalid value was supplied.
- `LifecycleExceptionInterface` — an illegal state transition or guard violation.
- `UnsupportedOperationExceptionInterface` — a behavior is not supported.

Consumers can catch any category marker to react to RunIt failures without
depending on a framework or a base class.