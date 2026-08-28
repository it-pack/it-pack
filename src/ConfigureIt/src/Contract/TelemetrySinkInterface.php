<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * Vendor-neutral OpenTelemetry-modeled telemetry sink.
 *
 * ConfigureIt emits configuration telemetry through this port. Two
 * implementations ship: {@see \ItPack\ConfigureIt\Bridge\OpenTelemetry\InMemoryTelemetrySink}
 * (recording/no-op, used in tests and as a default) and
 * {@see \ItPack\ConfigureIt\Bridge\OpenTelemetry\OtelTelemetrySink} (bridges to a
 * real OpenTelemetry SDK via {@see \ItPack\ConfigureIt\Bridge\OpenTelemetry\OpenTelemetryBridgeInterface}).
 *
 * Core depends only on this contract; no OpenTelemetry vendor code leaks past
 * the adapter/bridge layer.
 *
 * Promoted from `Bridge\OpenTelemetry\Contract\TelemetrySinkInterface` in
 * Milestone 6 (NV9b) so the port lives beside the other contracts. This is a
 * pre-1.0 internal API, so the old namespace was removed in the same release.
 */
interface TelemetrySinkInterface
{
    /**
     * Opens a resolution trace. Returns an opaque trace identifier used to
     * correlate subsequent spans and events.
     *
     * @param array<string, mixed> $attributes
     */
    public function startTrace(string $name, array $attributes): string;

    /**
     * Closes a previously opened trace.
     *
     * @param array<string, mixed> $attributes
     */
    public function endTrace(string $traceId, array $attributes): void;

    /**
     * Records a completed span belonging to a trace.
     *
     * @param array<string, mixed> $attributes
     */
    public function span(
        string $traceId,
        string $name,
        array $attributes,
        ?float $startedAt = null,
        ?float $endedAt = null,
    ): void;

    /**
     * Records a configuration lifecycle / observation event on a trace.
     *
     * @param array<string, mixed> $attributes
     */
    public function event(string $traceId, string $name, array $attributes): void;

    /**
     * Increments a counter metric.
     *
     * @param array<string, mixed> $attributes
     */
    public function increment(string $name, array $attributes, int $value = 1): void;

    /**
     * Records a measurement (histogram/gauge) metric.
     *
     * @param array<string, mixed> $attributes
     */
    public function measure(string $name, float $value, array $attributes): void;
}
