<?php

declare(strict_types=1);

namespace ItPack\RunIt\Behavior\Application;

use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Instant;

/**
 * Supplies an {@see InstantInterface} from the wrapped {@see MonotonicClockInterface}.
 *
 * Recorders and runners repeatedly need a wall-clock {@see Instant} for
 * timestamps. This behavior centralizes the `Instant::of($clock->nowSeconds())`
 * projection so the conversion lives in exactly one place and cannot drift.
 *
 * The using class must declare `private MonotonicClockInterface $clock`.
 *
 * @see https://w3id.org/itpack/runit#instant
 */
trait InstantClockBehavior
{
    protected function now(): InstantInterface
    {
        return Instant::of($this->clock->nowSeconds());
    }
}
