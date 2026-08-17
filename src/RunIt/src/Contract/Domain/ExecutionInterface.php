<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an immutable execution record.
 *
 * An execution record is the first artifact of an execution. It is produced by the
 * runtime, carries the immutable definition, the current status, timing, and an
 * optional result, and never retains a mutable execution session.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionInterface extends ExecutionArtifactInterface, ExecutionOwnedInterface, TimestampedInterface, SequencedInterface
{
    public function id(): ExecutionIdInterface;

    public function definition(): ExecutionDefinitionInterface;

    public function status(): ExecutionStatusInterface;

    public function startedAt(): InstantInterface;

    public function endedAt(): ?InstantInterface;

    public function result(): ?ExecutionResultInterface;
}
