<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance;

use Closure;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierValidatorInterface;

/**
 * Framework-agnostic material a port verifier needs.
 *
 * A verifier only depends on the port type and this fixture interface, so a
 * third-party adapter author supplies fixtures from their own domain objects
 * while reusing RunIt's verifiers. Implementers decide how to build the
 * artifacts.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface PortFixtureInterface
{
    public function safeObservation(): ObservationInterface;

    public function sensitiveObservation(): ObservationInterface;

    public function sensitiveAttributeValue(): string;

    public function measurement(): MeasurementInterface;

    public function occurrence(): OccurrenceInterface;

    public function snapshot(): SnapshotInterface;

    /**
     * @return Closure(SnapshotInterface): SnapshotInterface
     */
    public function snapshotPatcher(): Closure;

    public function stringIdentifierValidator(): StringIdentifierValidatorInterface;

    /**
     * @return list<ExecutionArtifactInterface>
     */
    public function samplingArtifacts(): array;
}
