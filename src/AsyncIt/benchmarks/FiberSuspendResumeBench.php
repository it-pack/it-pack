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
 * Fiber suspension/resumption benchmarks.
 *
 * Measures the cost of the Fiber suspend/resume hot path exercised by
 * {@see AsyncRuntime::awaitDelay()}: context detach/re-attach around
 * suspension, timer scheduling, and resumption on deadline expiry.
 *
 * @see https://w3id.org/itpack/asyncit#Awaitable
 * @see https://w3id.org/itpack/asyncit#Scheduler
 *
 * @BeforeMethods({"setUp"})
 * @Iterations(5)
 */
final class FiberSuspendResumeBench
{
    private AsyncRuntime $runtime;

    public function setUp(): void
    {
        $this->runtime = AsyncRuntime::discover();
    }

    /**
     * Suspend on `awaitDelay()` then resume once per rev.
     *
     * Exercises one full suspend/resume cycle plus monotonic-clock deadline
     * handling.
     *
     * @Revs(1000)
     */
    public function benchSuspendResumeOnce(): void
    {
        $this->runtime->run(
            new ReferenceExecutionScope('bench'),
            function (): int {
                $this->runtime->awaitDelay(Duration::ofNanoseconds(1));

                return 7;
            },
        );
    }

    /**
     * Suspend on `awaitDelay()` ten times per rev.
     *
     * Measures steady-state suspend/resume throughput across repeated
     * cooperation points.
     *
     * @Revs(500)
     */
    public function benchSuspendResumeTenTimes(): void
    {
        $this->runtime->run(
            new ReferenceExecutionScope('bench'),
            function (): int {
                $total = 0;

                for ($i = 0; $i < 10; $i++) {
                    $this->runtime->awaitDelay(Duration::ofNanoseconds(1));
                    $total++;
                }

                return $total;
            },
        );
    }
}
