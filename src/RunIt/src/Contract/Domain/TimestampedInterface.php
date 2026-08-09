<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

use Countable;

/**
 * Contract for artifacts or values that carry a point in time.
 *
 * The timestamp is expressed as Unix epoch seconds and returned as an
 * integer. This base contract deliberately keeps the projection primitive so
 * the domain layer introduces no clock or date abstraction here; the M1.4
 * milestone adds a dedicated immutable instant value object and this contract
 * will be widened accordingly before the stable `1.0.0` release.
 *
 * Durations are never part of this contract and always use monotonic time.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#startedAt
 */
interface TimestampedInterface extends Countable
{
    /**
     * Returns the Unix epoch seconds at which this artifact was produced, or
     * the point in time it refers to.
     *
     * @return int<0, max>
     */
    public function timestamp(): int;
}
