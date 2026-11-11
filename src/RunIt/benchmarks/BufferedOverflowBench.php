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

/** Measures DropOldest on a full count- or byte-limited buffer. */
#[OutputTimeUnit('microseconds')]
#[Warmup(0)]
#[Iterations(10)]
#[Revs(1)]
#[AfterMethods('verify')]
final class BufferedOverflowBench
{
    private const int ITEMS = 1024;
    private const int PUBLISHES = 128;

    private BufferedArtifactPublisher $publisher;
    private InMemoryArtifactPublisher $sink;
    private Snapshot $initial;
    private Snapshot $incoming;
    private int $bytes;

    /** Measure 128 count-triggered evictions. */
    #[Subject, BeforeMethods('countSetup')]
    public function benchCountOverflow(): void
    {
        $this->publishBatch();
    }

    /** Measure 128 byte-triggered evictions. */
    #[Subject, BeforeMethods('byteSetup')]
    public function benchByteOverflow(): void
    {
        $this->publishBatch();
    }

    /** Prepare a count-limited buffer. */
    public function countSetup(): void
    {
        $this->setUp(false);
    }

    /** Prepare a byte-limited buffer. */
    public function byteSetup(): void
    {
        $this->setUp(true);
    }

    /** Verify accounting and survivor order after the timed batch. */
    public function verify(): void
    {
        if ($this->publisher->bufferedCount() !== self::ITEMS
            || $this->publisher->estimatedBytes() !== self::ITEMS * $this->bytes
            || $this->publisher->droppedCount() !== self::PUBLISHES
        ) {
            throw new RuntimeException('Overflow accounting mismatch.');
        }
        $this->publisher->close();
        $expected = array_merge(
            array_fill(0, self::ITEMS - self::PUBLISHES, $this->initial),
            array_fill(0, self::PUBLISHES, $this->incoming),
        );
        if ($this->sink->published() !== $expected || $this->publisher->bufferedCount() !== 0
            || $this->publisher->estimatedBytes() !== 0 || $this->publisher->droppedCount() !== self::PUBLISHES
        ) {
            throw new RuntimeException('Overflow survivor order or cleanup mismatch.');
        }
    }

    /** Publish the timed batch against the prepared buffer. */
    private function publishBatch(): void
    {
        for ($i = 0; $i < self::PUBLISHES; ++$i) {
            $this->publisher->publish($this->incoming);
        }
    }

    /** Prepare equivalent artifacts and prefill the selected buffer. */
    private function setUp(bool $byteBound): void
    {
        $hash = new DeterministicHashCalculator();
        $this->initial = Snapshot::create(
            SnapshotId::fromString('snap-a'),
            ExecutionId::fromString('exec-bench'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['value' => 'initial!'],
            $hash,
            createdAt: Instant::of(1000),
        );
        $this->incoming = Snapshot::create(
            SnapshotId::fromString('snap-b'),
            ExecutionId::fromString('exec-bench'),
            SnapshotType::fromString('execution.snapshot'),
            1,
            ['value' => 'incoming'],
            $hash,
            createdAt: Instant::of(1000),
        );
        $estimator = new DefaultArtifactByteEstimator();
        $this->bytes = $estimator->estimate($this->initial);
        if ($this->bytes <= 0 || $this->bytes !== $estimator->estimate($this->incoming)) {
            throw new RuntimeException('Overflow fixture sizes must match.');
        }
        $this->sink = new InMemoryArtifactPublisher();
        $this->publisher = new BufferedArtifactPublisher(
            new BufferConfiguration(
                $byteBound ? self::ITEMS * 2 : self::ITEMS,
                ($byteBound ? self::ITEMS : self::ITEMS * 2) * $this->bytes,
                3600,
                self::ITEMS,
                0,
                self::ITEMS,
                OverflowStrategy::DropOldest,
            ),
            $this->sink,
            new SystemMonotonicClock(),
            $estimator,
        );
        for ($i = 0; $i < self::ITEMS; ++$i) {
            $this->publisher->publish($this->initial);
        }
    }
}
