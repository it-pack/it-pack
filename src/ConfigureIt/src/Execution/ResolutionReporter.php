<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Execution;

use ItPack\ConfigureIt\Contract\ResolutionReporterInterface;

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
     * @var list<Occurrence>
     */
    private array $occurrences = [];

    /**
     * @var list<Observation>
     */
    private array $observations = [];

    public function __construct(
        private readonly ?ExecutionContext $context = null,
    ) {
    }

    public function record(Occurrence $occurrence): void
    {
        $this->occurrences[] = $occurrence;
    }

    /**
     * @return list<Occurrence>
     */
    public function occurrences(): array
    {
        return $this->occurrences;
    }

    public function observe(Observation $observation): void
    {
        $this->observations[] = $observation;
    }

    /**
     * @return list<Observation>
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
