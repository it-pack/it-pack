<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for artifacts that are bound to an activity within an execution.
 *
 * Not every artifact is activity-bound: measurements, observations,
 * occurrences, and snapshots may record at the execution level, in which case
 * this contract reports no activity. The concrete identifier contract is
 * specialized per artifact by later milestones; this base contract returns the
 * shared string-identifier contract introduced with the base layer.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#activityId
 */
interface ActivityOwnedInterface
{
    /**
     * Returns the identifier of the activity this artifact is bound to, or
     * null when the artifact is bound to the execution directly.
     */
    public function activityId(): ?StringIdentifierInterface;
}
