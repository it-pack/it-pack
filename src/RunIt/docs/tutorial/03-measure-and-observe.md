# Tutorial 3 — Measurements & observations

**Goal:** quantify work with **measurements** and describe it with **observations**.

Build on [Part 2](02-activities.md): the scope you already have exposes `measurements()`
and `observations()` recorders. Both bind to the currently active activity
automatically.

## Measurements (quantitative)

A measurement is a named, typed, unit-bearing numeric value. Counters and sizes are
integers; gauges, durations, and ratios are floats. Durations use the monotonic clock.

```php
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;

$runtime->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('checkout.process'),
        ExecutionKind::fromString('operation'),
    ),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->measurements()->counter(MeasurementName::fromString('items'), 3, MeasurementUnit::Count);
        $scope->measurements()->duration(MeasurementName::fromString('elapsed'), 12.5, MeasurementUnit::Milliseconds);
        $scope->measurements()->gauge(MeasurementName::fromString('memory_mb'), 64.0, MeasurementUnit::Megabytes);
    },
);
```

> **Run this:** [`examples/measurements.php`](https://github.com/it-pack/run-it/blob/main/examples/measurements.php).

Measurements are structured artifacts, never preformatted metric strings — the unit
travels with the value, so downstream systems can aggregate correctly.

## Observations (qualitative)

An observation is a note: a message at a PSR-3 severity. A throwable is normalized to a
safe, bounded description — never a raw stack trace.

```php
use ItPack\RunIt\Domain\Observation\ObservationSeverity;

$runtime->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('checkout.process'),
        ExecutionKind::fromString('operation'),
    ),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->observations()->record('payment accepted', ObservationSeverity::Info);
        $scope->observations()->record('deprecated coupon code', ObservationSeverity::Warning);

        try {
            throw new RuntimeException('gateway timeout');
        } catch (Throwable $e) {
            $scope->observations()->record('payment failed', ObservationSeverity::Error, throwable: $e);
        }
    },
);
```

> **Run this:** [`examples/observations.php`](https://github.com/it-pack/run-it/blob/main/examples/observations.php).

## What you learned

- `measurements()` records counters, durations, and gauges with explicit units.
- `observations()` records messages at PSR-3 severities; throwables are safely normalized.
- Both bind to the active activity without extra wiring.

## Next

[Part 4 — Occurrences & snapshots](04-occurrences-and-snapshots.md): immutable facts and
state projections.
