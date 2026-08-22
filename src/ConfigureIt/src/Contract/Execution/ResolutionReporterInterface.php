<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\Execution;

/**
 * Emits execution-model events during resolution.
 *
 * Per docs/EXECUTION.md, the resolution pipeline is an execution. This reporter
 * records immutable Occurrences (ConfigurationLoaded, Merged, Validated,
 * Resolved, SnapshotCreated, Diagnostics) and Observations so that the process
 * is observable without coupling Core to any vendor telemetry standard.
 */
interface ResolutionReporterInterface
{
    public function record(OccurrenceInterface $occurrence): void;

    public function observe(ObservationInterface $observation): void;

    /**
     * @return list<OccurrenceInterface>
     */
    public function occurrences(): array;

    /**
     * @return list<ObservationInterface>
     */
    public function observations(): array;
}
