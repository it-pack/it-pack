<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract\Port;

use Fiber;

/**
 * Detects which concurrent runtime owns the current call stack.
 *
 * CommandIt keeps execution context isolated per concurrency primitive: a Fiber
 * or a coroutine runtime. A runtime-aware storage selector consults this detector
 * to choose the matching storage so the ambient scope never leaks across Fibers,
 * coroutines, or requests.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface RuntimeContextDetectorInterface
{
    /**
     * Returns the Fiber currently executing the call stack, or null when none.
     *
     * @return \Fiber<mixed, mixed, mixed, mixed>|null
     */
    public function currentFiber(): ?Fiber;

    /**
     * Whether the current call stack runs inside a Swoole/OpenSwoole coroutine
     * runtime. The default Fiber-only detector always returns false here.
     */
    public function isCoroutineRuntime(): bool;
}
