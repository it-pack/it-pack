<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

use Countable;

/**
 * Contract for artifacts that are ordered within their execution.
 *
 * Sequences increase monotonically within an execution and are used to order
 * artifacts deterministically. The projection is an integer for this base
 * contract; the M1.4 milestone introduces an immutable `ArtifactSequence` value
 * object and this contract will be widened accordingly before the stable
 * `1.0.0` release.
 *
 * A sequence is never expected to be negative.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#sequence
 */
interface SequencedInterface extends Countable
{
    /**
     * Returns the monotonic sequence number of this artifact within its
     * execution.
     */
    public function sequence(): int;
}
