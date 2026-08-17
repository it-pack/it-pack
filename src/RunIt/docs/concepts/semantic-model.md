# Semantic model (artifacts)

RunIt's domain layer is an immutable, serializable semantic model that exists
independently of any application runtime or framework. Every concept is a value
object or a small immutable record that never retains a mutable execution
session, a PSR service, or an adapter. This document covers the M3 artifacts:
execution, activity, measurement, observation, occurrence, and snapshot.

## Ownership, sequencing, and timing invariants

Every artifact shares a small set of base contracts so the runtime can treat
them uniformly:

| Contract | Responsibilities |
|---|---|
| `ExecutionArtifactInterface` | Marker for every artifact; exposes `normalize()` |
| `ExecutionOwnedInterface` | Reports the `ExecutionIdInterface` it belongs to |
| `ActivityOwnedInterface` | Reports an optional `ActivityIdInterface` (null at execution level) |
| `TimestampedInterface` | Reports a Unix epoch-second `timestamp()`; `Countable` |
| `SequencedInterface` | Reports a monotonic, non-negative `sequence()`; `Countable` |
| `AttributedInterface` | Exposes a bounded, recursively-primitive `attributes()` map |
| `NormalizableInterface` | Exposes a deterministic, bounded `normalize()` projection |

The relationship invariants are:

- **One execution per artifact.** Every artifact reports exactly one
  `executionId()`; ownership is expressed through identity, never through a
  retained owning collection or session.
- **Optional activity ownership.** Measurements, observations, occurrences, and
  activities that are bound to an activity report a non-null `activityId()`;
  execution-level artifacts report null.
- **Required sequence and timestamp.** Every artifact carries a non-negative
  `sequence()` and a positive `timestamp()`; these order artifacts
  deterministically within their execution.
- **No mutable sessions.** Artifact records are `readonly` and hold only
  identifiers, immutables, and bounded value collections. They never hold a
  runtime execution scope, clock, or recorder.
- **Deterministic normalization.** `normalize()` always returns the same output
  for the same object, never includes secret or restricted values unless an
  explicit policy allows them, never serializes arbitrary PHP objects, and stays
  bounded in size and cardinality.

## Artifact catalog

| Artifact | Identity | Ownership | Notes |
|---|---|---|---|
| `Execution` | `ExecutionId` | self | First artifact; carries immutable `ExecutionDefinition`, status, timing, optional result, context |
| `Activity` | `ActivityId` | execution + self | Carries `ActivityDefinition`, status, timing, attributes, optional throwable description |
| `Measurement` | `MeasurementName` | execution + optional activity | Typed, unit-bearing numeric value; counter/size are integers, gauge/duration/ratio are finite floats |
| `Observation` | `ObservationId` | execution + optional activity | Severity (PSR-3 levels), message, optional diagnostic code, attributes, optional throwable description |
| `Occurrence` | `OccurrenceId` | execution + optional activity | Stable versioned `OccurrenceType`, explicit source/subject, optional recursively-primitive data, extension attributes |
| `Snapshot` | `SnapshotId` | execution + optional activity | Classification (checkpoint/terminal), schema version, normalized payload, content hash, optional predecessor |

## Classification and redaction

Classifications (M2) and artifact normalization compose: a normalized artifact
carries only the stable, serializable projection of its data. Snapshots bind a
payload to a deterministic content hash (`HashCalculatorInterface`) so a store can
detect tampering and resolve predecessor chains without retaining every payload
inline.

## Normalization schema

`normalize()` for every artifact returns a deterministic, recursively-primitive
array. The common spine is:

```text
id            string   artifact identifier
execution_id  string   owning execution identifier
sequence      int      monotonic sequence within the execution
timestamp     int      Unix epoch seconds
```

Type-specific fields (status, kind, unit, severity, type, payload, attributes,
content hash, predecessor) extend this spine. The full schema is enforced by the
type system and covered by the unit tests under `tests/Unit/Domain`.
