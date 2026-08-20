<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\Execution;

use ItPack\ConfigureIt\Execution\Observation;
use ItPack\ConfigureIt\Execution\Occurrence;

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
    public function record(Occurrence $occurrence): void;

    public function observe(Observation $observation): void;

    /**
     * @return list<Occurrence>
     */
    public function occurrences(): array;

    /**
     * @return list<Observation>
     */
    public function observations(): array;
}
