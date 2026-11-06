<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Swoole;

use Fiber;
use ItPack\RunIt\Contract\Port\RuntimeContextDetectorInterface;
use Swoole\Coroutine;

/**
 * Detects Swoole/OpenSwoole coroutine context and Fiber context.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class SwooleRuntimeDetector implements RuntimeContextDetectorInterface
{
    public function inCoroutine(): bool
    {
        if (class_exists(Coroutine::class)) {
            return Coroutine::getCid() >= 0;
        }

        if (class_exists(\OpenSwoole\Coroutine::class)) {
            return \OpenSwoole\Coroutine::getCid() >= 0;
        }

        return false;
    }

    public function inFiber(): bool
    {
        return Fiber::getCurrent() instanceof Fiber;
    }
}
