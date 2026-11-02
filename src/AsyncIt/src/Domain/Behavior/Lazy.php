<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Behavior;

/**
 * Provides deferred one-time initialization.
 *
 * The {@see self::materialize()} method runs the supplied callable exactly
 * once, on first invocation. Subsequent calls are no-ops. Use this to defer
 * construction of expensive optional adapters until they are actually needed
 * ("lazy-loaded optional bridges").
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
trait Lazy
{
    private bool $initialized = false;

    /**
     * Runs `$initialize` once, on the first call. Subsequent calls are no-ops.
     */
    protected function materialize(callable $initialize): void
    {
        if ($this->initialized) {
            return;
        }

        $initialize();

        $this->initialized = true;
    }
}
