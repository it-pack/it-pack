<?php

declare(strict_types=1);

namespace Benchmarks;

use ItPack\RunIt\Adapter\Common\DefaultArtifactByteEstimator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\OverflowStrategy;
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

/** Measures normal admission, including real snapshot normalization and JSON estimation. */
#[OutputTimeUnit('microseconds')]
#[Warmup(2)]
#[Iterations(5)]
#[Revs(100)]
#[AfterMethods('verify')]
final class BufferedAdmissionBench
{
    private const int EXPECTED_PUBLISHES = 102;

    private BufferedArtifactPublisher $publisher;

    private Snapshot $snapshot;

    private int $bytes;

    #[Subject]
    #[BeforeMethods('setUpSmall')]
    public function benchSmall(): void
    {
        $this->publisher->publish($this->snapshot);
    }

    #[Subject]
    #[BeforeMethods('setUpLarge')]
    public function benchLarge(): void
    {
        $this->publisher->publish($this->snapshot);
    }

    public function setUpSmall(): void
    {
        $this->setUp(2);
    }

    public function setUpLarge(): void
    {
        $this->setUp(64);
    }

    public function verify(): void
    {
        if ($this->publisher->bufferedCount() !== self::EXPECTED_PUBLISHES
            || $this->publisher->estimatedBytes() !== self::EXPECTED_PUBLISHES * $this->bytes
            || $this->publisher->droppedCount() !== 0
        ) {
            throw new RuntimeException('Admission benchmark lifecycle or accounting mismatch.');
        }
    }

    private function setUp(int $entries): void
    {
        $items = [];
        for ($i = 0; $i < $entries; ++$i) {
            $items[] = ['id' => $i, 'details' => ['status' => 'ready', 'label' => str_repeat('x', 64)]];
        }

        $this->snapshot = Snapshot::create(
            SnapshotId::fromString('snap-bench'),
            ExecutionId::fromString('exec-bench'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['items' => $items, 'metadata' => ['source' => 'benchmark', 'valid' => true]],
            new DeterministicHashCalculator(),
            createdAt: Instant::of(1000),
        );
        $estimator = new DefaultArtifactByteEstimator();
        $this->bytes = $estimator->estimate($this->snapshot);
        $limit = self::EXPECTED_PUBLISHES + 1;
        $this->publisher = new BufferedArtifactPublisher(
            new BufferConfiguration($limit, $limit * $this->bytes, 60, $limit, 0, $limit, OverflowStrategy::Fail),
            new InMemoryArtifactPublisher(),
            new SystemMonotonicClock(),
            $estimator,
        );
    }
}
