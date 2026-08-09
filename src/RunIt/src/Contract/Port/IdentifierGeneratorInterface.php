<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

/**
 * Port supplying raw identifier values.
 *
 * Identity is an external effect: producing a unique value requires a source
 * of randomness, a clock, or a coordinated counter, none of which belong in
 * the domain. Adapters decide whether a value is a UUID, a ULID, a random hex
 * string, or a deterministic sequence used by tests.
 *
 * A generator must return a value accepted by
 * {@link \ItPack\RunIt\Contract\Domain\StringIdentifierValidatorInterface}:
 * non-empty, bounded, and free of whitespace and control characters.
 *
 * Implementations are expected to be safe to call repeatedly on a hot path.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
interface IdentifierGeneratorInterface
{
    /**
     * Returns a new raw identifier value.
     */
    public function generate(): string;
}
