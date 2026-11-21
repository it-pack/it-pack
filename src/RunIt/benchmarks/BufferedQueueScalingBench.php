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

/** Fixed-batch eviction and full/zero-budget close at increasing queue depths. */
#[OutputTimeUnit('microseconds'), Warmup(0), Iterations(10), Revs(1), AfterMethods('verify')]
final class BufferedQueueScalingBench
{
    private const int EVICTIONS = 64;
    private BufferedArtifactPublisher $publisher;
    private InMemoryArtifactPublisher $sink;
    private Snapshot $high;
    private Snapshot $low;
    private int $size;
    private string $operation;

    public function full256(): void
    {
        $this->setUp(256, 'full');
    }
    public function full1024(): void
    {
        $this->setUp(1024, 'full');
    }
    public function full4096(): void
    {
        $this->setUp(4096, 'full');
    }
    public function drop256(): void
    {
        $this->setUp(256, 'drop');
    }
    public function drop1024(): void
    {
        $this->setUp(1024, 'drop');
    }
    public function drop4096(): void
    {
        $this->setUp(4096, 'drop');
    }
    public function oldest256(): void
    {
        $this->setUp(256, 'oldest');
    }
    public function oldest1024(): void
    {
        $this->setUp(1024, 'oldest');
    }
    public function oldest4096(): void
    {
        $this->setUp(4096, 'oldest');
    }
    public function priority256(): void
    {
        $this->setUp(256, 'priority');
    }
    public function priority1024(): void
    {
        $this->setUp(1024, 'priority');
    }
    public function priority4096(): void
    {
        $this->setUp(4096, 'priority');
    }

    #[Subject, BeforeMethods('full256')]
    public function benchClose256(): void
    {
        $this->closeFull();
    }
    #[Subject, BeforeMethods('full1024')]
    public function benchClose1024(): void
    {
        $this->closeFull();
    }
    #[Subject, BeforeMethods('full4096')]
    public function benchClose4096(): void
    {
        $this->closeFull();
    }

    #[Subject, BeforeMethods('drop256')]
    public function benchDropClose256(): void
    {
        $this->closeDropped();
    }
    #[Subject, BeforeMethods('drop1024')]
    public function benchDropClose1024(): void
    {
        $this->closeDropped();
    }
    #[Subject, BeforeMethods('drop4096')]
    public function benchDropClose4096(): void
    {
        $this->closeDropped();
    }

    #[Subject, BeforeMethods('oldest256')]
    public function benchOldest256(): void
    {
        $this->evict();
    }
    #[Subject, BeforeMethods('oldest1024')]
    public function benchOldest1024(): void
    {
        $this->evict();
    }
    #[Subject, BeforeMethods('oldest4096')]
    public function benchOldest4096(): void
    {
        $this->evict();
    }

    #[Subject, BeforeMethods('priority256')]
    public function benchPriority256(): void
    {
        $this->evict();
    }
    #[Subject, BeforeMethods('priority1024')]
    public function benchPriority1024(): void
    {
        $this->evict();
    }
    #[Subject, BeforeMethods('priority4096')]
    public function benchPriority4096(): void
    {
        $this->evict();
    }

    private function setUp(int $size, string $operation): void
    {
        $this->size = $size;
        $this->high = $this->snapshot('high', SnapshotClassification::Terminal);
        $this->low = $this->snapshot('low', SnapshotClassification::Checkpoint);
        $this->prepare($operation);
    }

    private function prepare(string $operation): void
    {
        $this->operation = $operation;
        $this->sink = new InMemoryArtifactPublisher();
        $this->publisher = new BufferedArtifactPublisher(
            new BufferConfiguration(
                $this->size,
                8 * 1024 * 1024,
                3600,
                $this->size,
                0,
                $operation === 'drop' ? 0 : $this->size,
                $operation === 'priority' ? OverflowStrategy::DropLowPriority : OverflowStrategy::DropOldest
            ),
            $this->sink,
            new SystemMonotonicClock(),
            new DefaultArtifactByteEstimator(),
        );
        for ($i = 0; $i < $this->size; ++$i) {
            $this->publisher->publish($operation === 'priority' && $i < intdiv($this->size, 2) ? $this->high : $this->low);
        }
    }

    private function closeFull(): void
    {
        $this->publisher->close();
    }
    private function closeDropped(): void
    {
        $this->publisher->close();
    }
    private function evict(): void
    {
        for ($i = 0; $i < self::EVICTIONS; ++$i) {
            $this->publisher->publish($this->high);
        }
    }

    public function verify(): void
    {
        $drops = match ($this->operation) {
            'drop' => $this->size,
            'oldest', 'priority' => self::EVICTIONS,
            default => 0,
        };
        if ($this->operation === 'oldest' || $this->operation === 'priority') {
            if ($this->publisher->bufferedCount() !== $this->size || $this->publisher->droppedCount() !== $drops) {
                throw new RuntimeException('Eviction accounting mismatch.');
            }
            $this->publisher->close();
        }
        $expected = match ($this->operation) {
            'drop' => [],
            'oldest' => array_merge(array_fill(0, $this->size - self::EVICTIONS, $this->low), array_fill(0, self::EVICTIONS, $this->high)),
            'priority' => array_merge(array_fill(0, intdiv($this->size, 2), $this->high), array_fill(0, intdiv($this->size, 2) - self::EVICTIONS, $this->low), array_fill(0, self::EVICTIONS, $this->high)),
            default => array_fill(0, $this->size, $this->low),
        };
        if ($this->sink->published() !== $expected || $this->publisher->bufferedCount() !== 0
            || $this->publisher->estimatedBytes() !== 0 || $this->publisher->droppedCount() !== $drops) {
            throw new RuntimeException('Queue delivery or accounting mismatch.');
        }
    }

    private function snapshot(string $id, SnapshotClassification $classification): Snapshot
    {
        return Snapshot::create(
            SnapshotId::fromString('snap-' . $id),
            ExecutionId::fromString('exec-bench'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['value' => 'fixture'],
            new DeterministicHashCalculator(),
            classification: $classification,
            createdAt: Instant::of(1000)
        );
    }
}
