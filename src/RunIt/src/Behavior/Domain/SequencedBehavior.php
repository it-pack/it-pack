<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Domain;

/**
 * Projects an artifact sequence onto `Countable`.
 *
 * @see https://w3id.org/itpack/runit#sequence
 */
trait SequencedBehavior
{
    /**
     * @return int<0, max>
     */
    abstract public function sequence(): int;

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return $this->sequence();
    }
}
