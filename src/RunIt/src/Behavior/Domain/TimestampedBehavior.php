<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

/**
 * Projects an artifact timestamp onto `Countable`.
 *
 * @see https://w3id.org/itpack/runit#startedAt
 */
trait TimestampedBehavior
{
    /**
     * @return int<0, max>
     */
    abstract public function timestamp(): int;

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return $this->timestamp();
    }
}
