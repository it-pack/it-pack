# Activity

An **activity** is a bounded unit of work performed during an execution. It is the
RunIt-native counterpart of an OpenTelemetry span, but it is defined independently
of any external standard. The concept is defined by
[`runit:Activity`](https://w3id.org/itpack/runit#Activity).

> Activities perform work.

## What an activity represents

An activity names a discrete step inside an execution. Activities are optional but
expected for anything non-trivial: a request handler, a database query, a schema
validation pass, a YAML load, a merge step.

Examples:

```
Resolve configuration    Validate schema       Merge documents
Load YAML                Read resource         Build graph
Download secrets         Run workflow step
```

## Types

| Contract | Value object | Notes |
|---|---|---|
| `ActivityInterface` | `ItPack\RunIt\Domain\Activity\Activity` | Bounded unit of work |
| `ActivityDefinitionInterface` | `ItPack\RunIt\Domain\Activity\ActivityDefinition` | Immutable name, kind, parent id |
| `ActivityIdInterface` | `ItPack\RunIt\Domain\Activity\ActivityId` | Opaque stable identifier |
| `ActivityNameInterface` | `ItPack\RunIt\Domain\Activity\ActivityName` | Validated, extensible name |
| `ActivityKindInterface` | `ItPack\RunIt\Domain\Activity\ActivityKind` | Validated, extensible kind |
| `ActivityStatusInterface` | `ItPack\RunIt\Domain\Activity\ActivityStatus` | Closed lifecycle enum |

## Lifecycle

An activity shares the execution lifecycle vocabulary:

```
CREATED → RUNNING → SUCCEEDED | FAILED | CANCELLED | TIMED_OUT
```

`ActivityStatus` is the closed set `created`, `running`, `succeeded`, `failed`,
`cancelled`, `timed_out`. A completed (terminal) activity cannot be reopened. Open
activities must be resolved according to the execution's completion policy when the
execution completes.

## Nesting

Activities may be nested. A nested activity references its parent through
`parentActivityId()` on its `ActivityDefinition`; an execution-level activity has a
null parent. Nesting is expressed by identity — there is no mandatory in-memory
tree. Depth is bounded by the configured activity-depth limit
(`ActivityDepthExceededException` guards against runaway nesting).

## Ownership

- Every activity belongs to exactly one execution (`executionId()`).
- An activity may optionally own other artifacts (measurements, observations,
  occurrences) by reporting a matching `activityId()`.

## Normalization

`Activity::normalize()` returns a deterministic, recursively-primitive array:

```text
id                string   activity identifier
execution_id      string   owning execution identifier
name              string   activity name
kind              string   activity kind
status            string   lifecycle case name
started_at        int      Unix epoch seconds
ended_at          int|null Unix epoch seconds
parent_activity_id string|null
sequence          int      monotonic sequence within the execution
attributes        array    bounded attribute map
throwable         array|null normalized throwable description
```

## Invariants

- Activities are bounded units of work.
- An activity belongs to one execution.
- A nested activity references its parent activity explicitly.
- A completed activity cannot be reopened.
- Open activities are resolved according to an explicit completion policy.
- Attributes are bounded and recursively primitive (ADR-002).

## Public API usage

```php
declare(strict_types=1);

use ItPack\RunIt\Domain\Activity\Activity;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;

$activity = Activity::start(
    $executionId,
    ActivityDefinition::define('db.query', 'query'),
);
```

Consumers normally scope activities through `ActivityRunnerInterface` /
`ActivityScopeInterface`, which restores prior context in `finally` and records
failure safely. See [`README.md`](../README.md) for decorating callables.

## Related decisions

- ADR-001 — Execution is the root aggregate; activities belong to it.
- ADR-002 — Domain artifacts are immutable.
- ADR-003 — Identity and relationships express ownership.

See also: [`execution.md`](execution.md), [`measurement.md`](measurement.md),
[`observation.md`](observation.md).
