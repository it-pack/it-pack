<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Swoole;

use ItPack\RunIt\Adapter\Swoole\Contract\CoroutineIdProviderInterface;
use OpenSwoole\Coroutine as OpenSwooleCoroutine;
use Swoole\Coroutine as SwooleCoroutine;

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
        if (class_exists(SwooleCoroutine::class) && method_exists(SwooleCoroutine::class, 'getCid')) {
            return SwooleCoroutine::getCid();
        }

        if (class_exists(OpenSwooleCoroutine::class) && method_exists(OpenSwooleCoroutine::class, 'getCid')) {
            return OpenSwooleCoroutine::getCid();
        }

        return -1;
    }
}
