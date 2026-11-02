<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Benchmarks;

use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use PHPBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PHPBench\Benchmark\Metadata\Annotations\Iterations;
use PHPBench\Benchmark\Metadata\Annotations\Revs;

/**
 * Task spawn throughput benchmarks.
 *
 * Measures the cost of {@see AsyncRuntime::spawn()} (and, by extension, the
 * scheduler's {@see \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler::spawn()}
 * bookkeeping: record creation, Fiber wrapping, ready-queue enqueue, and
 * lifecycle publication).
 *
 * All spawn benchmarks run the operation inside {@see AsyncRuntime::run()} so
 * the numbers reflect the real scheduling path rather than a mocked scheduler.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/asyncit#Scheduler
 *
 * @BeforeMethods({"setUp"})
 * @Iterations(5)
 */
final class TaskSpawnBench
{
    private AsyncRuntime $runtime;

    public function setUp(): void
    {
        $this->runtime = AsyncRuntime::discover();
    }

    /**
     * Spawn a single trivial task per rev.
     *
     * Baseline spawn cost including reactor setup amortized across revs.
     *
     * @Revs(1000)
     */
    public function benchSpawnSingleTask(): void
    {
        $this->runtime->run(
            new ReferenceExecutionScope('bench'),
            function (): int {
                $task = $this->runtime->spawn(static fn (): int => 42);

                return $this->runtime->await($task);
            },
        );
    }

    /**
     * Spawn a batch of 100 trivial tasks per rev, then await all of them.
     *
     * Measures steady-state spawn throughput: record creation, Fiber wrapping,
     * and ready-queue enqueue per spawned task.
     *
     * @Revs(100)
     */
    public function benchSpawnBatch100(): void
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
