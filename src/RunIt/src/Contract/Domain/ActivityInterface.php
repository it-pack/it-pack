<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an immutable activity record.
 *
 * An activity record is produced by the activity runtime. It carries the immutable
 * definition, the current status, timing, a bounded attribute set, and an optional
 * safe throwable description, and never retains a mutable activity session.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
interface ActivityInterface extends ExecutionArtifactInterface, ExecutionOwnedInterface, ActivityOwnedInterface, TimestampedInterface, SequencedInterface, AttributedInterface
{
    public function id(): ActivityIdInterface;

    public function definition(): ActivityDefinitionInterface;

    public function status(): ActivityStatusInterface;

    public function startedAt(): InstantInterface;

    public function endedAt(): ?InstantInterface;

    public function throwableDescription(): ?ThrowableDescriptionInterface;

    /**
     * Returns a copy of this record carrying the terminal status, optional throwable
     * description, and end time.
     *
     * The receiver is never mutated. The activity runner is the canonical place to decide
     * whether completion is legal before calling this method.
     */
    public function complete(
        ActivityStatusInterface $status,
        ?ThrowableDescriptionInterface $throwableDescription,
        InstantInterface $endedAt
    ): ActivityInterface;
}
