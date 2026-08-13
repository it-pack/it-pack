<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an immutable, monotonic artifact sequence within one execution.
 *
 * A sequence numbers artifacts so they can be ordered deterministically. It is a
 * value: `next()` returns a new sequence and never mutates the receiver.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#sequence
 */
interface ArtifactSequenceInterface
{
    /**
     * Returns the numeric sequence value.
     *
     * @return int<0, max>
     */
    public function value(): int;

    /**
     * Returns the next sequence in the same execution, leaving this one
     * unchanged.
     */
    public function next(): self;
}
