<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

/**
 * Lazy stream of tasks produced by a generator, executed one-at-a-time on the
 * active scheduler.
 *
 * Each yielded callable is spawned as a task and awaited to completion before
 * the next item is pulled. Backpressure is applied naturally: the consumer
 * blocks on `next()` until the current task reaches a terminal state.
 *
 * Exposes the same method set as PHP's `AsyncIterator` (`current()`,
 * `key()`, `next()`, `rewind()`, `valid()`) so it can be consumed with a
 * standard `foreach` loop. Does not extend `\AsyncIterator` because that
 * interface is not available in this PHP version.
 *
 * The generator yields callables; each callable becomes a task via
 * {@see EngineSchedulerInterface::spawn()}. The reactor drains after each
 * spawn so the task runs to completion before the stream advances.
 *
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-kind-stream
 * @api
 */
interface TaskStreamInterface
{
    /**
     * Returns the result of the current task, or null when not positioned on
     * a valid item.
     */
    public function current(): mixed;

    /**
     * Returns the 0-based index of the current item.
     */
    public function key(): int;

    /**
     * Advances to the next item: pulls the next callable from the underlying
     * generator, spawns it as a task, runs it to completion, and stores its
     * result.
     */
    public function next(): void;

    /**
     * Rewinds the stream to the first item: pulls the first callable from the
     * generator, spawns it, runs it to completion.
     */
    public function rewind(): void;

    /**
     * Returns true when the stream is positioned on a valid item.
     */
    public function valid(): bool;
}
