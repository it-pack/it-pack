<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Swoole;

use ItPack\RunIt\Adapter\Swoole\Contract\CoroutineIdProviderInterface;

/**
 * Reads the coroutine id from Swoole or OpenSwoole.
 *
 * Both extensions expose an equivalent `\*\Coroutine::getCid()` API. When neither is
 * loaded the provider reports "not in a coroutine" so the storage degrades to its main-stack
 * behavior instead of fatal-erroring.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class SwooleCoroutineIdProvider implements CoroutineIdProviderInterface
{
    public function currentCoroutineId(): int
    {
        if (class_exists(\Swoole\Coroutine::class)) {
            $cid = \Swoole\Coroutine::getCid();

            return is_int($cid) ? $cid : -1;
        }

        if (class_exists(\OpenSwoole\Coroutine::class)) {
            $cid = \OpenSwoole\Coroutine::getCid();

            return is_int($cid) ? $cid : -1;
        }

        return -1;
    }
}
