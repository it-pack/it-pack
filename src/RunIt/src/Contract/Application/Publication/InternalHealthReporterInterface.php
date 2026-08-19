<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;

/**
 * Contract for a reporter that records internal publication failures.
 *
 * The health reporter is the safe sink for failures that must not recurse back
 * through the guarded publisher: recursion detection, dropped-artifact counters,
 * and fallback-report failures all land here. It must never call the guarded
 * publisher.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface InternalHealthReporterInterface
{
    /**
     * Records an internal publication failure.
     *
     * @param \Throwable $error the failure that occurred inside the pipeline
     */
    public function report(\Throwable $error, ExecutionArtifactInterface $artifact): void;
}
