<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Bridge\OpenTelemetry;

use ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract\OpenTelemetryBridgeInterface;
use ItPack\ConfigureIt\Contract\TelemetrySinkInterface;

/**
 * OpenTelemetry bridge sink.
 *
 * Implements {@see TelemetrySinkInterface} by forwarding every call to a
 * consumer-supplied {@see OpenTelemetryBridgeInterface}. The bridge is the only
 * place that knows about the OpenTelemetry SDK, satisfying the "Core never
 * depends on infrastructure" rule while delivering native OpenTelemetry
 * instrumentation (traces, metrics, configuration lifecycle events).
 */
final class OtelTelemetrySink implements TelemetrySinkInterface
{
    public function __construct(
        private readonly OpenTelemetryBridgeInterface $bridge,
    ) {
    }

    public function startTrace(string $name, array $attributes): string
    {
        return $this->bridge->startTrace($name, $attributes);
    }

    public function endTrace(string $traceId, array $attributes): void
    {
        $this->bridge->endTrace($traceId, $attributes);
    }

    public function span(
        string $traceId,
        string $name,
        array $attributes,
        ?float $startedAt = null,
        ?float $endedAt = null,
    ): void {
        $this->bridge->span($traceId, $name, $attributes, $startedAt, $endedAt);
    }

    public function event(string $traceId, string $name, array $attributes): void
    {
        $this->bridge->event($traceId, $name, $attributes);
    }

    public function increment(string $name, array $attributes, int $value = 1): void
    {
        $this->bridge->increment($name, $attributes, $value);
    }

    public function measure(string $name, float $value, array $attributes): void
    {
        $this->bridge->measure($name, $value, $attributes);
    }
}
