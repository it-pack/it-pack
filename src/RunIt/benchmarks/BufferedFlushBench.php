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

/** Measures a bounded flush and full-budget close from a full buffer. */
#[OutputTimeUnit('microseconds')]
#[Warmup(0)]
#[Iterations(10)]
#[Revs(1)]
#[BeforeMethods('setUp')]
#[AfterMethods('verify')]
final class BufferedFlushBench
{
    private const int ITEMS = 1024;

    private const int BATCH = 256;

    private BufferedArtifactPublisher $publisher;

    private InMemoryArtifactPublisher $sink;

    /** @var list<Snapshot> */
    private array $items;

    private int $remainingBytes;

    private bool $closed;

    public function setUp(): void
    {
        $estimator = new DefaultArtifactByteEstimator();
        $this->sink = new InMemoryArtifactPublisher();
        $this->publisher = new BufferedArtifactPublisher(
            new BufferConfiguration(self::ITEMS, 8 * 1024 * 1024, 3600, self::BATCH, 0, self::ITEMS, OverflowStrategy::Fail),
            $this->sink,
            new SystemMonotonicClock(),
            $estimator,
        );
        $this->items = [];
        $this->remainingBytes = 0;
        $this->closed = false;
        $hash = new DeterministicHashCalculator();
        for ($i = 0; $i < self::ITEMS; ++$i) {
            $item = Snapshot::create(
                SnapshotId::fromString('snap-' . $i),
                ExecutionId::fromString('exec-bench'),
                SnapshotType::fromString('execution.snapshot'),
                1,
                ['index' => $i],
                $hash,
                createdAt: Instant::of(1000),
            );
            $this->items[] = $item;
            $this->publisher->publish($item);
            if ($i >= self::BATCH) {
                $this->remainingBytes += $estimator->estimate($item);
            }
        }
    }

    #[Subject]
    public function benchFlush(): void
    {
        $this->publisher->flush();
    }

    #[Subject]
    /** Closes the prefilled buffer with a full delivery budget. */
    public function benchClose(): void
    {
        $this->publisher->close();
        $this->closed = true;
    }

    public function verify(): void
    {
        if (!$this->closed) {
            if ($this->sink->published() !== array_slice($this->items, 0, self::BATCH)
                || $this->publisher->bufferedCount() !== self::ITEMS - self::BATCH
                || $this->publisher->estimatedBytes() !== $this->remainingBytes
                || $this->publisher->droppedCount() !== 0
            ) {
                throw new RuntimeException('Buffered flush batch or accounting mismatch.');
            }

            $this->publisher->close();
        }
        if ($this->sink->published() !== $this->items || $this->publisher->bufferedCount() !== 0
            || $this->publisher->estimatedBytes() !== 0 || $this->publisher->droppedCount() !== 0
        ) {
            throw new RuntimeException('Buffered flush remainder mismatch.');
        }
    }
}
