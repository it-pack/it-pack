<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

use ItPack\RunIt\Common\Contract\StringableInterface;

/**
 * Contract for an immutable point in time.
 *
 * An instant is a domain value, not a raw integer projection. It is expressed
 * as Unix epoch seconds and carries no timezone; consumers that need a local
 * representation convert at the edge. Durations are measured with monotonic time
 * and are never expressed as an instant.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#instant
 */
interface InstantInterface extends StringableInterface
{
    /**
     * Returns the Unix epoch seconds represented by this instant.
     *
     * @return int<0, max>
     */
    public function epochSeconds(): int;
}
