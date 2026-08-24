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

    /**
     * Returns a copy of this record carrying the terminal result and end time.
     *
     * The receiver is never mutated. The aggregate itself enforces the closed
     * lifecycle matrix: a terminal receiver cannot be completed again, and a
     * transition not permitted by the matrix raises. The lifecycle coordinator
     * applies the same rules above the aggregate and additionally defines
     * idempotent re-completion semantics.
     */
    public function complete(ExecutionResultInterface $result, InstantInterface $endedAt): ExecutionInterface;

    /**
     * Returns a copy of this record carrying the running status.
     *
     * The receiver must be in the created state; the aggregate enforces this
     * directly and the lifecycle coordinator applies the same rule above it.
     */
    public function beginRunning(): ExecutionInterface;
}
