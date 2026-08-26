<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Poller;

use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;

/**
 * Native {@see PollerPortInterface} over PHP 8.6 `Io\Poll`.
 *
 * `Io\Poll` is the OS-readiness notifier shipped with PHP 8.6; this adapter
 * slides it into the very same PollerPort slot as the {@see SelectPoller}
 * fallback, so the scheduler and scheduling semantics are unchanged. The
 * adapter is edge-triggered: a stream is registered for READABLE readiness and
 * `await()` drives `Io\Poll::poll()`, re-probing each tick. Every registered
 * handle is tracked so conformance Assertion C observes deterministic cleanup,
 * and `release()` detaches the underlying watcher.
 *
 * The `Io\Poll` instance is injected (built in a Factory) so this adapter
 * performs no direct construction of runtime types. On runtimes without
 * `Io\Poll` the {@see \ItPack\AsyncIt\Application\Factory\PollerFactory} selects
 * the `SelectPoller` fallback instead, so the package stays fully usable.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
final class NativePoller implements PollerPortInterface
{
    /**
     * @var array<string, mixed> id => stream
     */
    private array $handles = [];

    /**
     * @var array<string, true> ids registered with the native poll
     */
    private array $nativeRegistered = [];

    private int $counter = 0;

    public function __construct(private readonly object $poll)
    {
    }

    public function register(mixed $stream): StreamPollHandle
    {
        $id = 'native-' . ++$this->counter;
        $this->handles[$id] = $stream;

        $this->registerWithPoll($id, $stream);

        return new StreamPollHandle($id);
    }

    public function release(StreamPollHandle $handle): void
    {
        $id = $handle->id;

        if (isset($this->nativeRegistered[$id])) {
            $stream = $this->handles[$id] ?? null;
            $this->unregisterWithPoll($stream);
            unset($this->nativeRegistered[$id]);
        }

        unset($this->handles[$id]);
    }

    public function await(int $timeoutNanoseconds): void
    {
        $milliseconds = (int) ceil($timeoutNanoseconds / 1_000_000);

        if (!method_exists($this->poll, 'poll')) {
            return;
        }

        /** @var callable $poll */
        $poll = [$this->poll, 'poll'];
        ($poll)($milliseconds);
    }

    public function activeHandleCount(): int
    {
        return count($this->handles);
    }

    private function registerWithPoll(string $id, mixed $stream): void
    {
        if (!is_resource($stream) || !method_exists($this->poll, 'register')) {
            return;
        }

        // 1 == Io\Poll::READABLE. Watch the stream for read readiness.
        /** @var callable $register */
        $register = [$this->poll, 'register'];
        $registered = ($register)($stream, 1);

        if ($registered === true) {
            $this->nativeRegistered[$id] = true;
        }
    }

    private function unregisterWithPoll(mixed $stream): void
    {
        if (!is_resource($stream) || !method_exists($this->poll, 'unregister')) {
            return;
        }

        /** @var callable $unregister */
        $unregister = [$this->poll, 'unregister'];
        ($unregister)($stream);
    }
}
