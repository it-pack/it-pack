<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

/**
 * An optional, lazily-loaded instrumentation bridge.
 *
 * Bridges (telemetry, tracing, metrics) are expensive and frequently unused;
 * the runtime exposes them behind a lazy proxy so construction of the
 * underlying recorder is deferred until the first {@see self::record()} call.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
interface TelemetryBridgeInterface
{
    /**
     * Records one runtime event. The first call lazily materialises the
     * underlying recorder.
     *
     * @param array<string, mixed> $context
     */
    public function record(string $event, array $context = []): void;

    /**
     * Whether the underlying recorder has been materialised yet.
     */
    public function isInitialized(): bool;
}
