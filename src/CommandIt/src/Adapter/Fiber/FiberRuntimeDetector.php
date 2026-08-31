<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Fiber;

use Fiber;
use ItPack\CommandIt\Contract\Port\RuntimeContextDetectorInterface;

/**
 * Detects only Fiber context; never reports a coroutine.
 *
 * @api
 */
final class FiberRuntimeDetector implements RuntimeContextDetectorInterface
{
    /**
     * @return Fiber<mixed, mixed, mixed, mixed>|null
     */
    public function currentFiber(): ?Fiber
    {
        return Fiber::getCurrent();
    }

    public function isCoroutineRuntime(): bool
    {
        return false;
    }
}
