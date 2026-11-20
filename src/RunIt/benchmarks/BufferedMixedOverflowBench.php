<?php

declare(strict_types=1);

namespace Benchmarks;

use ItPack\RunIt\Adapter\Common\DefaultArtifactByteEstimator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\BufferConfiguration;
use ItPack\RunIt\Application\Publication\BufferedArtifactPublisher;
use ItPack\RunIt\Application\Publication\OverflowStrategy;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Snapshot\Snapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
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
use Throwable;

/** Measures byte pressure with mixed sizes and priority eviction after a high-priority prefix. */
#[OutputTimeUnit('microseconds')]
#[Warmup(0)]
#[Iterations(10)]
#[Revs(1)]
#[AfterMethods('verify')]
final class BufferedMixedOverflowBench
{
    private const int ITEMS = 1024;
    private const int PUBLISHES = 128;

    private BufferedArtifactPublisher $publisher;
    private InMemoryArtifactPublisher $sink;
    private DefaultArtifactByteEstimator $estimator;
    private Snapshot $incoming;
    private bool $priority;

    #[Subject, BeforeMethods('mixedSetup')]
    public function benchMixedByteOverflow(): void
    {
        $this->publishBatch();
    }

    #[Subject, BeforeMethods('prioritySetup')]
    public function benchPriorityEviction(): void
    {
        $this->publishBatch();
    }

    /**
     * @throws InvalidValueExceptionInterface
     * @throws Throwable
     */
    public function mixedSetup(): void
    {
        $small = $this->snapshot('small', 16, false);
        $large = $this->snapshot('large', 2048, false);
        $this->incoming = $large;
        $this->estimator = new DefaultArtifactByteEstimator();
        $limit = intdiv(self::ITEMS, 2) * ($this->estimator->estimate($small) + $this->estimator->estimate($large));
        $this->setupPublisher(self::ITEMS * 2, $limit, OverflowStrategy::DropOldest);
        $this->priority = false;
        for ($i = 0; $i < self::ITEMS; ++$i) {
            $this->publisher->publish($i % 2 === 0 ? $small : $large);
        }
        if ($this->publisher->bufferedCount() !== self::ITEMS || $this->publisher->estimatedBytes() !== $limit) {
            throw new RuntimeException('Mixed-size prefill mismatch.');
        }
    }

    /**
     * @throws Throwable
     */
    public function prioritySetup(): void
    {
        $high = $this->snapshot('high', 16, true);
        $low = $this->snapshot('low', 16, false);
        $this->incoming = $high;
        $this->estimator = new DefaultArtifactByteEstimator();
        $this->setupPublisher(self::ITEMS, self::ITEMS * max($this->estimator->estimate($high), $this->estimator->estimate($low)), OverflowStrategy::DropLowPriority);
        $this->priority = true;
        for ($i = 0; $i < self::ITEMS; ++$i) {
            $this->publisher->publish($i < intdiv(self::ITEMS, 2) ? $high : $low);
        }
    }

    /**
     * @throws Throwable
     */
    public function verify(): void
    {
        $count = $this->publisher->bufferedCount();
        $bytes = $this->publisher->estimatedBytes();
        $drops = $this->publisher->droppedCount();
        if ($drops === 0 || $count + $drops !== self::ITEMS + self::PUBLISHES) {
            throw new RuntimeException('Overflow accounting mismatch.');
        }
        $this->publisher->close();
        $published = $this->sink->published();
        $actualBytes = 0;
        foreach ($published as $artifact) {
            $actualBytes += $this->estimator->estimate($artifact);
        }
        if ($actualBytes !== $bytes || $this->publisher->bufferedCount() !== 0 || count($published) !== $count
            || $this->publisher->estimatedBytes() !== 0 || $this->publisher->droppedCount() !== $drops
        ) {
            throw new RuntimeException('Overflow survivor accounting mismatch.');
        }
        if ($this->priority && ($count !== self::ITEMS || $drops !== self::PUBLISHES
            || count(array_filter($published, fn (Snapshot $snapshot): bool => $snapshot->classification()->isTerminal()))
                !== intdiv(self::ITEMS, 2) + self::PUBLISHES)) {
            throw new RuntimeException('Priority eviction mismatch.');
        }
    }

    /**
     * @throws Throwable
     */
    private function publishBatch(): void
    {
        for ($i = 0; $i < self::PUBLISHES; ++$i) {
            $this->publisher->publish($this->incoming);
        }
    }

    private function setupPublisher(int $items, int $bytes, OverflowStrategy $strategy): void
    {
        $this->sink = new InMemoryArtifactPublisher();
        $this->publisher = new BufferedArtifactPublisher(
            new BufferConfiguration($items, $bytes, 3600, self::ITEMS, 0, self::ITEMS, $strategy),
            $this->sink,
            new SystemMonotonicClock(),
            $this->estimator,
        );
    }

    /**
     * @throws InvalidValueExceptionInterface
     */
    private function snapshot(string $id, int $size, bool $terminal): Snapshot
    {
        return Snapshot::create(
            SnapshotId::fromString('snap-' . $id),
            ExecutionId::fromString('exec-bench'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['value' => str_repeat('x', $size)],
            new DeterministicHashCalculator(),
            classification: $terminal ? SnapshotClassification::Terminal : SnapshotClassification::Checkpoint,
            createdAt: Instant::of(1000),
        );
    }
}
