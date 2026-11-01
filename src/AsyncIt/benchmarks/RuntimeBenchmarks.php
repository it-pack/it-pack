<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Benchmarks;

use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PHPBench\Benchmark\Metadata\Annotations\Iterations;
use PHPBench\Benchmark\Metadata\Annotations\Revs;

/**
 * Task spawn/completion and Fiber suspension/resumption benchmarks.
 *
 * Uses the real runtime produced by {@see AsyncRuntime::discover()} so the
 * numbers reflect the actual scheduling path (FiberTaskScheduler + selected
 * reactor adapter) rather than a mocked scheduler.
 *
 * @see https://w3id.org/itpack/asyncit#Task
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/asyncit#Awaitable
 *
 * @BeforeMethods({"setUp"})
 * @Iterations(5)
 */
final class RuntimeBenchmarks
{
    private AsyncRuntime $runtime;

    public function setUp(): void
    {
        $this->runtime = AsyncRuntime::discover();
    }

    /**
     * Spawn + complete a single trivial task per rev.
     *
     * @Revs(1000)
     */
    public function benchRunSimpleOperation(): void
    {
        $this->runtime->run(
            new ReferenceExecutionScope('bench'),
            static fn (): int => 42,
        );
    }

    /**
     * Run a task that suspends on `awaitDelay()` then resumes.
     *
     * Exercises the Fiber suspend/resume hot path plus monotonic-clock
     * deadline handling.
     *
     * @Revs(1000)
     */
    public function benchRunWithAwaitDelay(): void
    {
        $this->runtime->run(
            new ReferenceExecutionScope('bench'),
            function (): int {
                $this->runtime->awaitDelay(Duration::ofNanoseconds(1));

                return 7;
            },
        );
    }
}
