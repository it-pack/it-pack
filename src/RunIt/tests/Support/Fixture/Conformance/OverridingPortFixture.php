<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Conformance;

use Closure;
use ItPack\RunIt\Conformance\Contract\PortFixtureInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierValidatorInterface;

/**
 * Fixture decorator that overrides selected {@see PortFixtureInterface} methods
 * so negative conformance tests can supply broken or impure material while the
 * remaining methods keep delegating to the wrapped fixture.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class OverridingPortFixture implements PortFixtureInterface
{
    /**
     * @param list<ExecutionArtifactInterface>|null $samplingArtifacts
     */
    public function __construct(
        private PortFixtureInterface $inner,
        private ?ObservationInterface $safeObservation = null,
        private ?ObservationInterface $sensitiveObservation = null,
        private ?SnapshotInterface $snapshot = null,
        private ?array $samplingArtifacts = null,
    ) {
    }

    public function safeObservation(): ObservationInterface
    {
        return $this->safeObservation ?? $this->inner->safeObservation();
    }

    public function sensitiveObservation(): ObservationInterface
    {
        return $this->sensitiveObservation ?? $this->inner->sensitiveObservation();
    }

    public function sensitiveAttributeValue(): string
    {
        return $this->inner->sensitiveAttributeValue();
    }

    public function measurement(): MeasurementInterface
    {
        return $this->inner->measurement();
    }

    public function occurrence(): OccurrenceInterface
    {
        return $this->inner->occurrence();
    }

    public function snapshot(): SnapshotInterface
    {
        return $this->snapshot ?? $this->inner->snapshot();
    }

    /**
     * @return Closure(SnapshotInterface): SnapshotInterface
     */
    public function snapshotPatcher(): Closure
    {
        return $this->inner->snapshotPatcher();
    }

    public function stringIdentifierValidator(): StringIdentifierValidatorInterface
    {
        return $this->inner->stringIdentifierValidator();
    }

    /**
     * @return list<ExecutionArtifactInterface>
     */
    public function samplingArtifacts(): array
    {
        return $this->samplingArtifacts ?? $this->inner->samplingArtifacts();
    }
}
