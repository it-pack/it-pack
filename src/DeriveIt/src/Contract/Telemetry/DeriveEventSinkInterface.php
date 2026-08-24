<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Telemetry;

/**
 * Emission port for derivation telemetry (CONSOLIDATION_PLAN.md §5.1, Option D).
 *
 * DeriveIt emits *facts only* — named activities, scalar measurements,
 * observations, and low-cardinality occurrences. It deliberately carries no
 * execution identity, timing, or context storage: those are host concerns.
 *
 * The method set mirrors the shape of RunIt's `ExecutionScopeInterface`
 * recorders so a bridge implementation maps every call mechanically onto
 * `activities()/measurements()/observations()/occurrences()` without either
 * package importing the other. The default sink is {@see NullDeriveEventSink};
 * wiring anything else is opt-in via the composition root.
 *
 * All attribute values must be scalars or null so events stay trivially
 * serializable (CloudEvents-ready) and low-cardinality by convention.
 */
interface DeriveEventSinkInterface
{
    /**
     * A named stage of work began (e.g. `graph.walk`, `surface.extract`).
     *
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public function activityStarted(string $name, array $attributes = []): void;

    /**
     * A previously started stage finished. Failed stages are intentionally not
     * emitted: derivations fail fast by throwing, and mapping a throwable onto
     * telemetry is the host's decision.
     *
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public function activityFinished(string $name, bool $successful = true, array $attributes = []): void;

    /**
     * A countable fact (e.g. `methods.derived`, `source.bytes`).
     *
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public function measured(string $name, int|float $value, string $unit, array $attributes = []): void;

    /**
     * A qualitative notice on a non-fatal path (fatal problems throw instead).
     *
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public function observed(string $message, DeriveObservationSeverity $severity, array $attributes = []): void;

    /**
     * An immutable fact that happened (e.g. `derivation.completed`,
     * `drift.detected`). Types are low-cardinality dotted identifiers.
     *
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public function occurred(string $type, array $attributes = []): void;
}
