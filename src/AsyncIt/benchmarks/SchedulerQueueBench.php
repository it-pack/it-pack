<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Benchmarks;

use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use PHPBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PHPBench\Benchmark\Metadata\Annotations\Iterations;
use PHPBench\Benchmark\Metadata\Annotations\Revs;

/**
 * Scheduler queue throughput benchmarks.
 *
 * Measures the cost of the reactor's ready-queue: enqueue on spawn/timer
 * expiry/waiter resumption and dequeue on each reactor step. Implemented by
 * spawning a batch of tasks that all sit on the ready queue before running to
 * completion.
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 *
 * @BeforeMethods({"setUp"})
 * @Iterations(5)
 */
final class SchedulerQueueBench
{
    private AsyncRuntime $runtime;

    public function setUp(): void
    {
        $this->runtime = AsyncRuntime::discover();
    }

    /**
     * Spawn 100 tasks that enqueue onto the ready queue, then run them to
     * completion in a single reactor pass.
     *
     * Measures enqueue+dequeue throughput under a full ready queue.
     *
     * @Revs(100)
     */
    public function benchReadyQueueBatch100(): void
    {
        $count = 100;
        $this->runtime->run(
            new ReferenceExecutionScope('bench'),
            function () use ($count): int {
                $tasks = [];

                for ($i = 0; $i < $count; $i++) {
                    $tasks[] = $this->runtime->spawn(static fn (): int => $i);
                }

                $total = 0;

                foreach ($tasks as $task) {
                    $total += $this->runtime->await($task);
                }

                return $total;
            },
        );
    }
}
