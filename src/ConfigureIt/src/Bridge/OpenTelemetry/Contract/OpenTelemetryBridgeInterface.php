<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Bridge\OpenTelemetry\Contract;

/**
 * The real OpenTelemetry transport, supplied by the consuming application.
 *
 * ConfigureIt must not depend on the OpenTelemetry SDK (it is infrastructure),
 * so the bridge is injected by the consumer — typically a thin wrapper around
 * `open-telemetry/opentelemetry-api` that maps these calls onto
 * Tracer/Meter/Logger. This keeps the SDK an adapter concern while ConfigureIt
 * owns the OTel *semantics* (see {@see \ItPack\ConfigureIt\Bridge\OpenTelemetry\SemanticConventions}).
 */
interface OpenTelemetryBridgeInterface
{
    /**
     * Starts the root trace span and returns its trace identifier.
     *
     * @param array<string, mixed> $attributes
     */
    public function startTrace(string $name, array $attributes): string;

    /**
     * Ends the root trace span identified by $traceId.
     *
     * @param array<string, mixed> $attributes
     */
    public function endTrace(string $traceId, array $attributes): void;

    /**
     * Records a completed span under $traceId.
     *
     * @param array<string, mixed> $attributes
     */
    public function span(
        string $traceId,
        string $name,
        array $attributes,
        ?float $startedAt,
        ?float $endedAt,
    ): void;

    /**
     * Records an event (CloudEvents-style lifecycle event or log/observation)
     * on the trace identified by $traceId.
     *
     * @param array<string, mixed> $attributes
     */
    public function event(string $traceId, string $name, array $attributes): void;

    /**
     * Increments a counter instrument.
     *
     * @param array<string, mixed> $attributes
     */
    public function increment(string $name, array $attributes, int $value): void;

    /**
     * Records a measurement on a histogram/gauge instrument.
     *
     * @param array<string, mixed> $attributes
     */
    public function measure(string $name, float $value, array $attributes): void;
}
