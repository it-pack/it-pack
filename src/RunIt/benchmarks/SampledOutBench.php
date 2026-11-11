<?php

declare(strict_types=1);

namespace Benchmarks;

use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\AlwaysSamplePolicy;
use ItPack\RunIt\Application\Publication\NeverSamplePolicy;
use ItPack\RunIt\Application\Publication\SamplingArtifactPublisher;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotId;
use ItPack\RunIt\Domain\Snapshot\SnapshotType;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use PhpBench\Attributes\Warmup;
use RuntimeException;

/** Measures a batch of rejected snapshot publications without health reporting. */
#[OutputTimeUnit('microseconds')]
#[Warmup(0), Iterations(10), Revs(1)]
#[BeforeMethods('setUp'), AfterMethods('verify')]
final class SampledOutBench
{
    private SamplingArtifactPublisher $publisher;
    private InMemoryArtifactPublisher $sink;
    private Snapshot $snapshot;

    public function setUp(): void
    {
        $this->sink = new InMemoryArtifactPublisher();
        $this->publisher = new SamplingArtifactPublisher(new NeverSamplePolicy(), $this->sink);
        $this->snapshot = Snapshot::create(
            SnapshotId::fromString('sampled-out'),
            ExecutionId::fromString('exec-bench'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['status' => 'ok', 'items' => [1, 2, 3]],
            new DeterministicHashCalculator(),
            createdAt: Instant::of(1000),
        );
        $control = new InMemoryArtifactPublisher();
        new SamplingArtifactPublisher(new AlwaysSamplePolicy(), $control)->publish($this->snapshot);
        if ($control->published() !== [$this->snapshot]) {
            throw new RuntimeException('Sampled-in control did not reach the sink.');
        }
    }

    /** Publish 128 preconstructed artifacts through the rejection path. */
    #[Subject]
    public function benchRejectedBatch(): void
    {
        for ($i = 0; $i < 128; ++$i) {
            $this->publisher->publish($this->snapshot);
        }
    }

    /** Check that no rejected artifact reached the sink. */
    public function verify(): void
    {
        if ($this->sink->published() !== []) {
            throw new RuntimeException('Sampled-out artifact reached the sink.');
        }
    }
}
