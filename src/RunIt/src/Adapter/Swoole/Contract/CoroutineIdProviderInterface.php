<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Swoole\Contract;

/**
 * Supplies the current coroutine identity.
 *
 * Abstracting coroutine identity keeps the storage testable without the Swoole extension
 * and lets the same storage serve both Swoole and OpenSwoole.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface CoroutineIdProviderInterface
{
    /**
     * Returns the current coroutine id, or a negative value when not in a coroutine.
     */
    public function currentCoroutineId(): int;
}
