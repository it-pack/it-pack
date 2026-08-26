<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an immutable measurement artifact.
 *
 * A measurement records a named, typed, unit-bearing numeric value at a point in
 * time within an execution. It is produced by recorders and never retains a mutable
 * session.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#measurement
 */
interface MeasurementInterface extends
    ExecutionArtifactInterface,
    ExecutionOwnedInterface,
    ActivityOwnedInterface,
    TimestampedInterface,
    SequencedInterface,
    AttributedInterface
{
    public function name(): MeasurementNameInterface;

    public function kind(): MeasurementKindInterface;

    public function unit(): MeasurementUnitInterface;

    /**
     * @return int|float the measured value
     */
    public function value(): int|float;

    /**
     * Returns the activity this measurement is bound to, or null when recorded at
     * the execution level.
     */
    public function activityId(): ?StringIdentifierInterface;
}
