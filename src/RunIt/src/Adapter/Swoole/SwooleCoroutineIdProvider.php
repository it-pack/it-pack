<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Swoole;

use Closure;
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
    private const int NON_ID = -1;

    /** @phpstan-ignore-next-line */
    private static Closure $getCid;

    public function __construct()
    {
        $this->setup();
    }


    public function currentCoroutineId(): int
    {
        /** @phpstan-ignore return.type */
        return (self::$getCid)();
    }

    /** @noinspection PhpUndefinedClassInspection */
    private function setup(): void
    {
        if (isset(self::$getCid)) {
            return;
        }

        /** @phpstan-ignore class.notFound */
        foreach ([SwooleCoroutine::class, OpenSwooleCoroutine::class] as $candidate) {
            if (class_exists($candidate)
                && method_exists($candidate, 'getCid')
            ) {
                /** @phpstan-ignore-next-line */
                self::$getCid = $candidate::getCid(...);

                return;
            }
        }

        /** @phpstan-ignore-next-line */
        self::$getCid = static fn (): int => self::NON_ID;
    }
}
