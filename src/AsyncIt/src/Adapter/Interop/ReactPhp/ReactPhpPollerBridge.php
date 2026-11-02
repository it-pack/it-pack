<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Interop\ReactPhp;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Domain\Poller\StreamSelectPollerTrait;

/**
 * ReactPHP interop {@see PollerPortInterface} bridge.
 *
 * Lets an existing ReactPHP event loop host AsyncIt tasks: readiness watchers
 * are registered on the supplied `React\EventLoop\LoopInterface` when the
 * descriptor is watchable, so AsyncIt joins the host loop. AsyncIt's scheduler
 * still drives suspension through PHP Fibers and probes readiness with
 * `await(0)`, so this bridge also performs a portable `stream_select()`
 * readiness check inside `await()` — the same model as the Swoole/Revolt
 * pollers. Every registered handle is tracked so conformance Assertion C
 * observes deterministic cleanup.
 *
 * This is interop, not unification: AsyncIt's scheduling semantics are unchanged
 * and no ReactPHP type appears in the production port surface. The loop is
 * injected (an `object` standing in for `React\EventLoop\LoopInterface`) so the
 * bridge has no hard dependency on the host library and keeps the package
 * analysable where ReactPHP is absent.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
final class ReactPhpPollerBridge implements PollerPortInterface
{
    use StreamSelectPollerTrait;

    public function __construct(private readonly object $loop)
    {
    }

    protected function getHandleIdPrefix(): string
    {
        return 'react-';
    }

    protected function registerWithReactor(string $id, mixed $stream): void
    {
        $this->registerWithLoop($id, $stream);
    }

    protected function releaseFromReactor(mixed $callbackId): void
    {
        $stream = $callbackId;

        if (!is_resource($stream) || !method_exists($this->loop, 'removeReadStream')) {
            return;
        }

        /** @var callable $remove */
        $remove = [$this->loop, 'removeReadStream'];
        ($remove)($stream);
    }

    private function registerWithLoop(string $id, mixed $stream): void
    {
        if (!is_resource($stream) || !method_exists($this->loop, 'addReadStream')) {
            return;
        }

        /** @var callable $add */
        $add = [$this->loop, 'addReadStream'];
        ($add)($stream, static fn (): null => null);

        $this->registered[$id] = $stream;
    }
}
