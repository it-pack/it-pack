<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for artifacts and values that expose a deterministic, bounded
 * normalization.
 *
 * Normalization is the RunIt-native projection of an immutable value into a
 * plain, recursively primitive array. It must:
 *
 *  - always return the same output for the same input object (determinism);
 *  - never include secret or restricted values unless an explicit policy allows
 *    them;
 *  - never serialize arbitrary PHP objects;
 *  - remain bounded in size and cardinality;
 *  - be independent of any external standard, adapter, or framework.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#
 */
interface NormalizableInterface
{
    /**
     * Returns a deterministic, recursively primitive projection of this value.
     *
     * Values are limited to scalars, null, and lists/maps of scalar values.
     *
     * @return array<string, mixed> a deterministic, primitive projection
     */
    public function normalize(): array;
}
