<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveObservationSeverity;

/**
 * Records every emitted telemetry fact as `[kind, name, attributes]` tuples so
 * tests can assert exact event sequences (CONSOLIDATION_PLAN.md §5.2 / C2).
 * R4-7 / F-5 — extracted from DerivationTelemetryTest + DeriveEventEmitterTest.
 */
final class RecordingDeriveEventSink implements DeriveEventSinkInterface
{
    /** @var list<array{string, string, array<string, string|int|float|bool|null>}> */
    public array $events = [];

    public function activityStarted(string $name, array $attributes = []): void
    {
        $this->events[] = ['activity.started', $name, $attributes];
    }

    public function activityFinished(string $name, bool $successful = true, array $attributes = []): void
    {
        $this->events[] = ['activity.finished', $name, ['successful' => $successful] + $attributes];
    }

    public function measured(string $name, int|float $value, string $unit, array $attributes = []): void
    {
        $this->events[] = ['measured', $name, ['value' => $value, 'unit' => $unit] + $attributes];
    }

    public function observed(string $message, DeriveObservationSeverity $severity, array $attributes = []): void
    {
        $this->events[] = ['observed', $severity->value, ['message' => $message] + $attributes];
    }

    public function occurred(string $type, array $attributes = []): void
    {
        $this->events[] = ['occurred', $type, $attributes];
    }

    /**
     * @return list<string> `kind:name` pairs, the event skeleton in order.
     */
    public function kinds(): array
    {
        return array_map(static fn (array $event): string => $event[0] . ':' . $event[1], $this->events);
    }
}
