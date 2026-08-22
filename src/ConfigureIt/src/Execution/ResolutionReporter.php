<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Execution;

use ItPack\ConfigureIt\Contract\Execution\ObservationInterface;
use ItPack\ConfigureIt\Contract\Execution\OccurrenceInterface;
use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;

/**
 * Default in-memory resolution reporter.
 *
 * Records Occurrences (and, optionally, Observations) emitted by the resolver
 * during a resolution execution. Framework-free and vendor-free, satisfying
 * docs/EXECUTION.md without coupling Core to OpenTelemetry or any other standard.
 */
final class ResolutionReporter implements ResolutionReporterInterface
{
    /**
     * @var list<OccurrenceInterface>
     */
    private array $occurrences = [];

    /**
     * @var list<ObservationInterface>
     */
    private array $observations = [];

    public function __construct(
        private readonly ?ExecutionContext $context = null,
    ) {
    }

    public function record(OccurrenceInterface $occurrence): void
    {
        $this->occurrences[] = $occurrence;
    }

    /**
     * @return list<OccurrenceInterface>
     */
    public function occurrences(): array
    {
        return $this->occurrences;
    }

    public function observe(ObservationInterface $observation): void
    {
        $this->observations[] = $observation;
    }

    /**
     * @return list<ObservationInterface>
     */
    public function observations(): array
    {
        return $this->observations;
    }

    public function context(): ?ExecutionContext
    {
        return $this->context;
    }
}
