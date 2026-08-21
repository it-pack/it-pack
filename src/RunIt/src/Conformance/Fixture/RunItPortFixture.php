<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Fixture;

use Closure;
use ItPack\RunIt\Conformance\Contract\PortFixtureInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierValidatorInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use ItPack\RunIt\Domain\Occurrence\OccurrenceId;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Shared\StringIdentifierValidator;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;

/**
 * RunIt-supplied fixture material for the built-in port verifiers.
 *
 * It builds every artifact from the RunIt domain so the shipped verifiers can be
 * exercised against the bundled adapters. Third-party adapters provide their own
 * {@see PortFixtureInterface} implementation with equivalent artifacts.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class RunItPortFixture implements PortFixtureInterface
{
    public function safeObservation(): ObservationInterface
    {
        return Observation::info(
            ObservationId::fromString('obs-0001'),
            ExecutionId::fromString('exec-0001'),
            'Service started',
            attributes: AttributeCollection::fromArray(['component' => 'bootstrap']),
            instant: Instant::of(1_700_000_000),
        );
    }

    public function sensitiveObservation(): ObservationInterface
    {
        return Observation::info(
            ObservationId::fromString('obs-secret'),
            ExecutionId::fromString('exec-0001'),
            'Connected to upstream',
            attributes: AttributeCollection::fromArray(['password' => $this->sensitiveAttributeValue()]),
            instant: Instant::of(1_700_000_000),
        );
    }

    public function sensitiveAttributeValue(): string
    {
        return 'hunter2';
    }

    public function measurement(): MeasurementInterface
    {
        return Measurement::counter(
            MeasurementName::fromString('items.processed'),
            ExecutionId::fromString('exec-0001'),
            3,
            instant: Instant::of(1_700_000_000),
        );
    }

    public function occurrence(): OccurrenceInterface
    {
        return Occurrence::create(
            OccurrenceId::fromString('occ-0001'),
            ExecutionId::fromString('exec-0001'),
            OccurrenceType::fromString('system.boot.v1'),
            'runit.runtime',
            Instant::of(1_700_000_000),
            ['started' => true],
        );
    }

    public function snapshot(): SnapshotInterface
    {
        return Snapshot::create(
            SnapshotId::fromString('snap-0001'),
            ExecutionId::fromString('exec-0001'),
            SnapshotType::fromString('configuration.snapshot'),
            1,
            ['feature_flags' => ['beta' => true]],
            new DeterministicHashCalculator(),
            createdAt: Instant::of(1_700_000_000),
        );
    }

    /**
     * @return Closure(SnapshotInterface): SnapshotInterface
     */
    public function snapshotPatcher(): Closure
    {
        return function (SnapshotInterface $snapshot): SnapshotInterface {
            return Snapshot::create(
                $snapshot->id(),
                $snapshot->executionId(),
                $snapshot->type(),
                $snapshot->schemaVersion(),
                ['feature_flags' => ['beta' => false]],
                new DeterministicHashCalculator(),
                createdAt: Instant::of($snapshot->timestamp()),
            );
        };
    }

    public function stringIdentifierValidator(): StringIdentifierValidatorInterface
    {
        return new StringIdentifierValidator();
    }

    /**
     * @return list<ExecutionArtifactInterface>
     */
    public function samplingArtifacts(): array
    {
        return [
            $this->safeObservation(),
            $this->occurrence(),
            $this->measurement(),
            $this->snapshot(),
        ];
    }
}
