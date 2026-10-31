<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use Generator;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\TaskStreamInterface;

/**
 * Lazy stream of tasks produced by a generator, executed one-at-a-time on the
 * active scheduler.
 *
 * Each yielded callable is spawned as a task and awaited to completion before
 * the next item is pulled. Backpressure is applied naturally: the consumer
 * blocks on `next()` until the current task reaches a terminal state.
 *
 * The generator yields callables; each callable becomes a task via
 * {@see EngineSchedulerInterface::spawn()} and is awaited via
 * {@see EngineSchedulerInterface::await()} so the reactor stays single-looped
 * (no recursive {@see run()} calls).
 *
 * Results are evicted once the stream advances past them, so the buffer never
 * grows beyond the current item.
 *
 * @see https://w3id.org/itpack/asyncit-taxonomy#task-kind-stream
 */
final class TaskStream implements TaskStreamInterface
{
    /**
     * @var array<int, mixed>
     */
    private array $results = [];

    private int $index = -1;

    private bool $finished = false;

    public function __construct(
        private readonly EngineSchedulerInterface $scheduler,
        private readonly Generator $generator,
    ) {
    }

    public function current(): mixed
    {
        if ($this->index < 0 || !array_key_exists($this->index, $this->results)) {
            return null;
        }

        return $this->results[$this->index];
    }

    public function key(): int
    {
        return $this->index;
    }

    public function next(): void
    {
        if ($this->finished) {
            return;
        }

        $this->index++;

        if (!$this->generator->valid()) {
            $this->finished = true;
            return;
        }

        // Evict results for indices we have moved past; keep only the current one.
        foreach (array_keys($this->results) as $key) {
            if ($key < $this->index) {
                unset($this->results[$key]);
            }
        }

        /** @var callable(): mixed $operation */
        $operation = $this->generator->current();
        $task = $this->scheduler->spawn($operation);

        $this->results[$this->index] = $this->scheduler->await($task);
        $this->generator->next();
    }

    public function rewind(): void
    {
        $this->index = -1;
        $this->finished = false;
        $this->results = [];
        $this->generator->rewind();
        $this->next();
    }

    public function valid(): bool
    {
        return !$this->finished && $this->index >= 0 && array_key_exists($this->index, $this->results);
    }
}
