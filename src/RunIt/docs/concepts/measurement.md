# Measurement

A **measurement** is a quantitative observation of work. It captures a named,
typed, unit-bearing numeric value attached to an execution or activity. The
concept is defined by [`runit:Measurement`](https://w3id.org/itpack/runit#Measurement).

> Measurements quantify work.

## What a measurement represents

A measurement answers "how much?" — how long, how big, how many. It is the
RunIt-native counterpart of a metric sample, but it is not defined in terms of any
metric backend.

Examples:

```
Duration        Memory           CPU
Files loaded    Documents parsed Variables resolved
Graph nodes     Validation errors
```

## Types

| Contract | Value object | Notes |
|---|---|---|
| `MeasurementInterface` | `ItPack\RunIt\Domain\Measurement\Measurement` | Quantitative artifact |
| `MeasurementNameInterface` | `ItPack\RunIt\Domain\Measurement\MeasurementName` | Validated, extensible name |
| `MeasurementKindInterface` | `ItPack\RunIt\Domain\Measurement\MeasurementKind` | Closed kind enum |
| `MeasurementUnitInterface` | `ItPack\RunIt\Domain\Measurement\MeasurementUnit` | Closed unit enum |

`MeasurementKind` is the closed set `counter`, `gauge`, `duration`, `size`,
`ratio`. `MeasurementUnit` is the closed set `count`, `bytes`, `kilobytes`,
`megabytes`, `milliseconds`, `seconds`, `minutes`, `percent`, `ratio`,
`celsius`.

Counters and sizes are integers; gauges, durations, and ratios are finite floats.

## Ownership

- Every measurement belongs to exactly one execution (`executionId()`).
- A measurement may be bound to an activity (`activityId()`), or be execution-level
  (`null`).

## Normalization

`Measurement::normalize()` returns a deterministic, recursively-primitive array:

```text
name         string   measurement name
kind         string   measurement kind (enum case name)
unit         string   measurement unit (enum case name)
value        int|float numeric value
execution_id string   owning execution identifier
activity_id  string|null
timestamp    int      Unix epoch seconds
sequence     int      monotonic sequence within the execution
attributes   array    bounded attribute map
```

## Invariants

- Measurements are quantitative.
- Each measurement has a name, numeric value, and unit.
- High-cardinality values must not become metric dimensions by default; keep
  dimensional attributes bounded.
- Duration uses monotonic time (ADR-009). The `duration` kind is recorded with a
  monotonic clock through the `MonotonicClockInterface` port.
- Values are bounded; attribute maps are recursively primitive.

## Public API usage

```php
declare(strict_types=1);

use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementKind;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;

$measurement = Measurement::record(
    MeasurementName::fromString('files.loaded'),
    MeasurementKind::Counter,
    MeasurementUnit::Count,
    12,
    $executionId,
);
```

Consumers normally record through `MeasurementRecorderInterface`, which attributes
the measurement to the active execution (and activity) scope.

## Related decisions

- ADR-002 — Domain artifacts are immutable.
- ADR-009 — Monotonic duration time.
- ADR-018 — Extensible string names and kinds (names are validated strings, not
  closed enums).

See also: [`execution.md`](execution.md), [`activity.md`](activity.md).
