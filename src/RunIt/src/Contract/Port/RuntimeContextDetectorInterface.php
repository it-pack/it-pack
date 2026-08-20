<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

/**
 * Detects which concurrent runtime owns the current call stack.
 *
 * RunIt keeps execution context isolated per concurrency primitive: a Fiber, a
 * Swoole/OpenSwoole coroutine, or the plain synchronous call stack. A runtime-aware
 * storage selector consults this detector to choose the matching storage so the ambient
 * scope never leaks across Fibers, coroutines, or requests.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface RuntimeContextDetectorInterface
{
    /**
     * Whether the current call stack runs inside an active Swoole/OpenSwoole coroutine.
     */
    public function inCoroutine(): bool;

    /**
     * Whether the current call stack runs inside a PHP Fiber.
     */
    public function inFiber(): bool;
}
