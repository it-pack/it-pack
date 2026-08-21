# Observation

An **observation** is a qualitative description of work — a log message, warning,
diagnostic, or error note. It is the RunIt-native counterpart of a PSR-3 log
record or an OpenTelemetry log record, but it is defined independently of any
external standard. The concept is defined by
[`runit:Observation`](https://w3id.org/itpack/runit#Observation).

> Observations describe work.

## What an observation represents

An observation answers "what did we notice?" — a human- or machine-readable note
about the execution. It is not an event: observations are qualitative, occurrences
(see [`occurrence.md`](occurrence.md)) are immutable facts.

Examples:

```
Log message       Warning            Validation diagnostic
Debug message     Resolution report  Error note
```

## Types

| Contract | Value object | Notes |
|---|---|---|
| `ObservationInterface` | `ItPack\RunIt\Domain\Observation\Observation` | Qualitative artifact |
| `ObservationIdInterface` | `ItPack\RunIt\Domain\Observation\ObservationId` | Opaque stable identifier |
| `ObservationSeverityInterface` | `ItPack\RunIt\Domain\Observation\ObservationSeverity` | Closed severity enum (PSR-3 levels) |

`ObservationSeverity` is the closed set `trace`, `debug`, `info`, `notice`,
`warning`, `error`, `critical`, `alert`, `emergency`. It carries a PSR-3-aligned
`level()` integer and an `isError()` predicate (`level() >= error`).

## Ownership

- Every observation belongs to exactly one execution (`executionId()`).
- An observation may be bound to an activity (`activityId()`), or be execution-level
  (`null`).

## Normalization

`Observation::normalize()` returns a deterministic, recursively-primitive array:

```text
id              string   observation identifier
execution_id    string   owning execution identifier
activity_id     string|null
severity        string   severity case name (PSR-3 level)
level           int      numeric PSR-3 level
message         string   message text
diagnostic_code string|null
sequence        int      monotonic sequence within the execution
timestamp       int      Unix epoch seconds
attributes      array    bounded attribute map
throwable       array|null normalized throwable description
```

## Invariants

- Observations are qualitative.
- Prefer stable message templates and structured attributes over interpolated
  free text.
- Throwable data must be safely normalized (`ThrowableDescription`), never raw
  objects or unbounded stack traces.
- Observations must not become occurrences merely because they are important.
  Use an occurrence when a stable, versioned fact should be recorded.
- Attributes are bounded and recursively primitive.

## Public API usage

```php
declare(strict_types=1);

use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;

$observation = Observation::note(
    $executionId,
    ObservationSeverity::Warning,
    'config file missing; using defaults',
);
```

Consumers normally record through `ObservationRecorderInterface`, which attributes
the observation to the active execution (and activity) scope and applies redaction
before publication.

## Related decisions

- ADR-002 — Domain artifacts are immutable.
- ADR-006 — External standards are adapters (PSR-3 maps to observations).
- ADR-018 — Extensible string names and kinds.

See also: [`execution.md`](execution.md), [`occurrence.md`](occurrence.md).
