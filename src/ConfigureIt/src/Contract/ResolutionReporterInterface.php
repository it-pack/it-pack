<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Execution\Occurrence;

/**
 * Emits execution-model events during resolution.
 *
 * Per docs/EXECUTION.md, the resolution pipeline is an execution. This reporter
 * records immutable Occurrences (ConfigurationLoaded, Merged, Validated,
 * Resolved, SnapshotCreated) and Observations so that the process is
 * observable without coupling Core to any vendor telemetry standard.
 */
interface ResolutionReporterInterface
{
    public function record(Occurrence $occurrence): void;

    /**
     * @return list<Occurrence>
     */
    public function occurrences(): array;
}
