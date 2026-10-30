<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Poller;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Domain\Poller\StreamSelectPollerTrait;
use ItPack\AsyncIt\Domain\StreamPollHandle;

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

    /**
     * @var array<string, mixed> id => stream
     */
    private array $handles = [];

    private int $counter = 0;

    public function register(mixed $stream): StreamPollHandleInterface
    {
        $id = 'select-' . ++$this->counter;
        $this->handles[$id] = $stream;

        return new StreamPollHandle($id);
    }

    public function release(StreamPollHandleInterface $handle): void
    {
        unset($this->handles[$handle->id]);
    }

    public function await(int $timeoutNanoseconds): void
    {
        $this->probeReadiness($this->handles, $timeoutNanoseconds);
    }

    public function activeHandleCount(): int
    {
        return count($this->handles);
    }
}
