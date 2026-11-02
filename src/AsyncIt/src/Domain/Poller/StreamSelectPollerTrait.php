<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Poller;

use ItPack\AsyncIt\Contract\Port\PollerVocabulary;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Domain\StreamPollHandle;
use ValueError;

/**
 * Shared readiness probe, handle bookkeeping, and `await()`/`readyHandles()`
 * lifecycle for stream-based pollers.
 *
 * Provides a stateless, portable {@see stream_select()} readiness probe plus
 * the handle registry state (`$handles`, `$counter`) and the identical
 * `await()`/`readyHandles()` lifecycle used by every poller whose readiness
 * reduces to a stream-select check. Each consumer supplies its own
 * reactor-specific prefix and `register()`/`release()` hooks via the abstract
 * methods below; the shared `register()`/`release()` handle ID generation,
 * handle storage, reactor registration/release, and handle cleanup.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
trait StreamSelectPollerTrait
{
    /**
     * @var array<string, mixed> id => stream resources registered
     */
    private array $handles = [];

    /**
     * @var array<string, mixed> ids registered with the reactor (value is
     *                             reactor-specific, e.g. a callback id or the
     *                             stream itself)
     */
    private array $registered = [];

    /**
     * @var list<mixed> streams that became readable during the last await() call
     */
    private array $readyReadStreams = [];

    /**
     * @var list<mixed> streams that became writable during the last await() call
     */
    private array $readyWriteStreams = [];

    private int $counter = 0;

    public function register(mixed $stream): StreamPollHandleInterface
    {
        $id = $this->getHandleIdPrefix() . ++$this->counter;
        $this->handles[$id] = $stream;
        $this->registerWithReactor($id, $stream);

        return new StreamPollHandle($id);
    }

    public function release(StreamPollHandleInterface $handle): void
    {
        $id = $handle->id;

        try {
            if (isset($this->registered[$id])) {
                $this->releaseFromReactor($this->registered[$id]);
            }
        } finally {
            unset($this->registered[$id], $this->handles[$id]);
        }
    }

    public function await(int $timeoutNanoseconds): void
    {
        $readiness = $this->probeReadiness($this->handles, $timeoutNanoseconds);
        $this->readyReadStreams = $readiness['read'];
        $this->readyWriteStreams = $readiness['write'];
    }

    public function readyHandles(): array
    {
        $ready = $this->matchReadyHandles(
            $this->handles,
            $this->readyReadStreams,
            $this->readyWriteStreams,
        );

        $this->readyReadStreams = [];
        $this->readyWriteStreams = [];

        return $ready;
    }

    public function activeHandleCount(): int
    {
        return count($this->handles);
    }

    /**
     * Per-adapter handle id prefix, e.g. `'select-'`, `'native-'`, `'revolt-'`.
     */
    abstract protected function getHandleIdPrefix(): string;

    /**
     * Registers the stream with the runtime reactor, if supported. Stores any
     * reactor-specific bookkeeping value in {@see $registered} keyed by $id so
     * {@see releaseFromReactor()} can remove it later.
     */
    abstract protected function registerWithReactor(string $id, mixed $stream): void;

    /**
     * Releases the reactor watcher identified by the value previously stored
     * in {@see $registered} for $id.
     */
    abstract protected function releaseFromReactor(mixed $callbackId): void;

    /**
     * Blocks until at least one handle is ready for reading or writing, or the
     * monotonic timeout (nanoseconds) elapses.
     *
     * Uses {@see PollerVocabulary::NANOSECONDS_PER_SECOND} and
     * {@see PollerVocabulary::NANOSECONDS_PER_MICROSECOND} for the conversion
     * so no magic numbers appear in consuming pollers.
     *
     * @param array<string, mixed> $handles id => stream resources registered
     * @return array{read: list<mixed>, write: list<mixed>} ready streams per
     *         readiness kind
     *
     * @see https://w3id.org/itpack/asyncit#Poller
     */
    final protected function probeReadiness(array $handles, int $timeoutNanoseconds): array
    {
        $read = [];
        $write = [];

        foreach ($handles as $stream) {
            if (is_resource($stream)) {
                $read[] = $stream;
                $write[] = $stream;
            }
        }

        if ($read === [] && $write === []) {
            return ['read' => [], 'write' => []];
        }

        $seconds = intdiv($timeoutNanoseconds, PollerVocabulary::NANOSECONDS_PER_SECOND);
        $microseconds = intdiv(
            $timeoutNanoseconds % PollerVocabulary::NANOSECONDS_PER_SECOND,
            PollerVocabulary::NANOSECONDS_PER_MICROSECOND
        );

        $except = [];

        try {
            $readyCount = @stream_select($read, $write, $except, $seconds, $microseconds);
        } catch (ValueError) {
            // Invalid stream_select arguments; treat as no readiness.
            return ['read' => [], 'write' => []];
        }

        if ($readyCount === false) {
            // stream_select failed (e.g. invalid descriptor); treat as no
            // readiness so the scheduler re-probes on the next tick.
            return ['read' => [], 'write' => []];
        }

        return ['read' => $read, 'write' => $write];
    }

    /**
     * Matches registered handles against readiness arrays produced by
     * {@see probeReadiness()} and returns the handles that are ready for
     * reading or writing. Clears neither array — callers manage array state.
     *
     * @param array<string, mixed> $handles id => stream resources registered
     * @param list<mixed>          $readStreams streams ready for reading
     * @param list<mixed>          $writeStreams streams ready for writing
     * @return list<StreamPollHandleInterface> handles that became ready
     *
     * @see https://w3id.org/itpack/asyncit#StreamPollHandle
     */
    final protected function matchReadyHandles(
        array $handles,
        array $readStreams,
        array $writeStreams,
    ): array {
        $ready = [];

        foreach ($handles as $id => $stream) {
            if (!is_resource($stream)) {
                continue;
            }

            $isReadReady = in_array($stream, $readStreams, strict: true);
            $isWriteReady = in_array($stream, $writeStreams, strict: true);

            if ($isReadReady || $isWriteReady) {
                $ready[] = new StreamPollHandle($id);
            }
        }

        return $ready;
    }
}
