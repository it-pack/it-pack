<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Behavior\Domain;

/**
 * Projects a monotonic artifact timestamp onto {@see \Countable}.
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
    // @phpstan-ignore-next-line shipmonk.deadMethod Foundation trait projection; consumed by Milestone 2 artifacts.
    public function count(): int
    {
        return $this->timestamp();
    }
}
