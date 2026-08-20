<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Fiber;

use Fiber;
use ItPack\RunIt\Contract\Port\RuntimeContextDetectorInterface;

/**
 * Detects only Fiber context; never reports a coroutine.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class FiberRuntimeDetector implements RuntimeContextDetectorInterface
{
    public function inCoroutine(): bool
    {
        return false;
    }

    public function inFiber(): bool
    {
        return Fiber::getCurrent() !== null;
    }
}
