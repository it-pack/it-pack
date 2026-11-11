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

    private string $operation;

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
        $this->operation = 'flush';
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
        $this->operation = 'close';
    }

    /** Drain the entire buffer in four bounded batches. */
    #[Subject]
    public function benchRepeatedFlush(): void
    {
        for ($i = 0; $i < self::ITEMS / self::BATCH; ++$i) {
            $this->publisher->flush();
        }
        $this->operation = 'close';
    }

    /** Prepare the same buffer with a limited shutdown delivery budget. */
    public function limitedSetup(): void
    {
        $this->setUp();
        $estimator = new DefaultArtifactByteEstimator();
        $this->sink = new InMemoryArtifactPublisher();
        $this->publisher = new BufferedArtifactPublisher(
            new BufferConfiguration(self::ITEMS, 8 * 1024 * 1024, 3600, self::BATCH, 0, self::BATCH, OverflowStrategy::Fail),
            $this->sink,
            new SystemMonotonicClock(),
            $estimator,
        );
        foreach ($this->items as $item) {
            $this->publisher->publish($item);
        }
    }

    /** Close and drop items beyond the limited shutdown budget. */
    #[Subject, BeforeMethods('limitedSetup')]
    public function benchLimitedClose(): void
    {
        $this->publisher->close();
        $this->operation = 'limited';
    }

    public function verify(): void
    {
        if ($this->operation === 'limited') {
            if ($this->sink->published() !== array_slice($this->items, 0, self::BATCH)
                || $this->publisher->bufferedCount() !== 0 || $this->publisher->estimatedBytes() !== 0
                || $this->publisher->droppedCount() !== self::ITEMS - self::BATCH
            ) {
                throw new RuntimeException('Limited close delivery or accounting mismatch.');
            }

            return;
        }
        if ($this->operation === 'flush') {
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
