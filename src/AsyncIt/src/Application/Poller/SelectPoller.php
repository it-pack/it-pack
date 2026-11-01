<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Poller;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Domain\Poller\StreamSelectPollerTrait;

/**
 * Reference poller backed by `stream_select()`.
 *
 * It implements the {@see PollerPortInterface} readiness contract for the
 * reference runtime: handles are tracked until released so conformance
 * Assertion C can observe deterministic cleanup. It never pretends to be the
 * native PHP 8.6 `Io\Poll` API; it is a weaker, development-grade fallback with
 * the same behavioural contract.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
final class SelectPoller implements PollerPortInterface
{
    use StreamSelectPollerTrait;

    protected function getHandleIdPrefix(): string
    {
        return 'select-';
    }

    protected function registerWithReactor(string $id, mixed $stream): void
    {
        // No reactor: select-based poller has no reactor registration.
    }

    protected function releaseFromReactor(mixed $callbackId): void
    {
        // No reactor: nothing to release.
    }
}
