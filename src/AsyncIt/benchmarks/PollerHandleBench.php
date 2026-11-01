<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Benchmarks;

use ItPack\AsyncIt\Application\Poller\SelectPoller;
use PHPBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PHPBench\Benchmark\Metadata\Annotations\Iterations;
use PHPBench\Benchmark\Metadata\Annotations\Revs;

/**
 * Poll-handle registration/teardown benchmarks.
 *
 * Measures the per-handle bookkeeping performed on every await boundary:
 * {@see SelectPoller::register()}, {@see SelectPoller::release()}, and
 * active-handle tracking.
 *
 * @see https://w3id.org/itpack/asyncit#Poller
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 *
 * @BeforeMethods({"setUp"})
 * @Iterations(5)
 */
final class PollerHandleBench
{
    private SelectPoller $poller;

    /**
     * @var resource
     */
    private $stream;

    public function setUp(): void
    {
        $this->poller = new SelectPoller();
        $this->stream = fopen('php://temp', 'rb+');
    }

    /**
     * Register a handle then release it, per rev.
     *
     * @Revs(10000)
     */
    public function benchRegisterRelease(): void
    {
        $handle = $this->poller->register($this->stream);
        $this->poller->release($handle);
    }

    /**
     * Register a batch of 100 handles then release all 100, per rev.
     *
     * Measures handle-table growth and teardown throughput under load.
     *
     * @Revs(1000)
     */
    public function benchRegisterReleaseBatch100(): void
    {
        $handles = [];

        for ($i = 0; $i < 100; $i++) {
            $handles[] = $this->poller->register($this->stream);
        }

        foreach ($handles as $handle) {
            $this->poller->release($handle);
        }
    }
}
