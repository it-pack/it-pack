<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Interop\Amp;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Domain\Poller\StreamSelectPollerTrait;

/**
 * Amp interop {@see PollerPortInterface} bridge.
 *
 * Lets an existing Amp event loop host AsyncIt tasks: readiness watchers are
 * registered on `Amp\Loop` (the Amp event-loop facade) when the descriptor is
 * watchable, so AsyncIt joins the host loop. AsyncIt's scheduler still drives
 * suspension through PHP Fibers and probes readiness with `await(0)`, so this
 * bridge also performs a portable `stream_select()` readiness check inside
 * `await()` — the same model as the Swoole/Revolt pollers. Every registered
 * handle is tracked so conformance Assertion C observes deterministic cleanup.
 *
 * This is interop, not unification: AsyncIt's scheduling semantics are unchanged
 * and no Amp type appears in the production port surface. `Amp\Loop` is reached
 * through a guarded, reflection-free facade reference so the bridge has no hard
 * dependency on the host library and keeps the package analysable where Amp is
 * absent.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
final class AmpPollerBridge implements PollerPortInterface
{
    use StreamSelectPollerTrait;

    protected function getHandleIdPrefix(): string
    {
        return 'amp-';
    }

    protected function registerWithReactor(string $id, mixed $stream): void
    {
        $this->registerWithLoop($id, $stream);
    }

    protected function releaseFromReactor(mixed $callbackId): void
    {
        $watcherId = $callbackId;

        if (!is_string($watcherId)) {
            return;
        }

        $this->cancelOnLoop($watcherId);
    }

    private function registerWithLoop(string $id, mixed $stream): void
    {
        $amp = 'Amp\\Loop';

        if (!is_resource($stream) || !class_exists($amp)) {
            return;
        }

        /** @var callable $onReadable */
        $onReadable = $amp . '::onReadable';
        $watcherId = ($onReadable)($stream, static fn (): null => null);

        if (is_string($watcherId) && $watcherId !== '') {
            $this->registered[$id] = $watcherId;
        }
    }

    private function cancelOnLoop(string $watcherId): void
    {
        $amp = 'Amp\\Loop';

        if (!class_exists($amp)) {
            return;
        }

        /** @var callable $cancel */
        $cancel = $amp . '::cancel';
        ($cancel)($watcherId);
    }
}
