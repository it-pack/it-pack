<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an immutable observation artifact.
 *
 * An observation records qualitative information noticed during execution: a message
 * template, an optional diagnostic code, a bounded attribute set, and an optional
 * safe throwable description. Observations never retain a mutable session.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
interface ObservationInterface extends
    ExecutionArtifactInterface,
    ExecutionOwnedInterface,
    ActivityOwnedInterface,
    TimestampedInterface,
    SequencedInterface,
    AttributedInterface
{
    public function id(): ObservationIdInterface;

    public function severity(): ObservationSeverityInterface;

    public function message(): string;

    public function diagnosticCode(): ?string;

    public function throwableDescription(): ?ThrowableDescriptionInterface;
}
