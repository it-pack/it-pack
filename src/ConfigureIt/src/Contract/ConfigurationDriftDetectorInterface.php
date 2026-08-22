<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\ValueObject\ConfigurationDiff;

/**
 * Detects runtime drift between the live configuration graph and a pinned baseline.
 *
 * The detector is told how to obtain the *current* graph (a provider callable)
 * and compares it, on demand or on an interval, against a baseline graph that is
 * pinned once (via the constructor or {@see self::pin()}). When the live graph
 * diverges, a {@see ConfigurationDiff} is produced, an `Observation` is recorded
 * (forwarded as a `config.drift` OpenTelemetry event) and registered listeners
 * are invoked. Reuses {@see ConfigurationDiffer} (Plan §4 / Epic E4.2).
 *
 * Drift is reported on *transition* (entering drift, further change while already
 * in drift, or a return to baseline) so an interval loop does not spam telemetry.
 */
interface ConfigurationDriftDetectorInterface
{
    /**
     * Pin the current live graph as the baseline.
     *
     * Subsequent checks only report drift relative to this snapshot.
     */
    public function pin(): void;

    /**
     * Compare the live graph against the baseline.
     *
     * @return bool true when the live graph currently diverges from the baseline.
     */
    public function check(): bool;

    /**
     * Blocking loop: run {@see self::check()} repeatedly until {@see self::stop()}.
     */
    public function watch(): void;

    /**
     * Request the {@see self::watch()} loop to stop at the next iteration.
     */
    public function stop(): void;

    /**
     * Register a listener invoked with (diff, live, baseline) on each new drift
     * transition.
     *
     * @param callable(ConfigurationDiff, ConfigurationGraphInterface, ConfigurationGraphInterface): void $listener
     */
    public function onDrift(callable $listener): void;
}
