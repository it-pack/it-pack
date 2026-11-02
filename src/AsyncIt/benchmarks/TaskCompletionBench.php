<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Benchmarks;

use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use PHPBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PHPBench\Benchmark\Metadata\Annotations\Iterations;
use PHPBench\Benchmark\Metadata\Annotations\Revs;

/**
 * Task completion throughput benchmarks.
 *
 * Measures the cost of moving a task through its terminal lifecycle:
 * {@see \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler::complete()},
 * including result storage, duration measurement, lifecycle publication,
 * poll-handle/timer release, and waiter resumption.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/asyncit#Scheduler
 *
 * @BeforeMethods({"setUp"})
 * @Iterations(5)
 */
final class TaskCompletionBench
{
    private AsyncRuntime $runtime;

    public function setUp(): void
    {
        $this->runtime = AsyncRuntime::discover();
    }

    /**
     * Run and complete a single trivial task per rev.
     *
     * @Revs(1000)
     */
    public function benchCompleteSingleTask(): void
    {
        $this->runtime->run(
            new ReferenceExecutionScope('bench'),
            static fn (): int => 42,
        );
    }

    /**
     * Spawn a batch of 100 tasks and await each to completion per rev.
     *
     * Measures per-completion cost under concurrent completion, including
     * waiter resumption and ready-queue re-enqueue.
     *
     * @Revs(100)
     */
    public function benchCompleteBatch100(): void
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
