<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\BatchFlushObserverInterface;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Artifact publisher that buffers artifacts and flushes them in bounded batches.
 *
 * The buffer is hard-bounded by item count and estimated bytes, so memory never grows
 * without limit under sustained load. When it is full the configured {@see OverflowStrategy}
 * decides what to drop. Flush emits at most `batchSize` non-expired artifacts; `close()`
 * flushes within `shutdownFlushBudget` and drops whatever remains. Dropped and expired
 * artifacts are reported to the internal health reporter so loss stays observable without
 * re-entering the guarded pipeline.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class BufferedArtifactPublisher implements ArtifactPublisherInterface, FlushablePublisherInterface, CloseablePublisherInterface
{
    private const int BLOCK_BUDGET_MICROSECONDS = 2000;

    /**
     * @var array<int, array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int}>
     */
    private array $buffer = [];

    private int $estimatedBytes = 0;

    private int $droppedCount = 0;

    private int $lastFlushEpoch = 0;

    /** @var \Closure(): int */
    private readonly Closure $nowSeconds;

    /** @var \Closure(ExecutionArtifactInterface): int */
    private readonly Closure $byteEstimator;

    /**
     * @param Closure(ExecutionArtifactInterface): int|null $estimateBytes
     */
    public function __construct(
        private readonly BufferConfiguration $config,
        private readonly ArtifactPublisherInterface $inner,
        private readonly ?InternalHealthReporterInterface $health = null,
        ?Closure $nowSeconds = null,
        ?Closure $estimateBytes = null,
        private readonly ?BatchFlushObserverInterface $observer = null,
    ) {
        $this->nowSeconds = $nowSeconds ?? static fn (): int => time();
        $this->byteEstimator = $estimateBytes ?? static fn (ExecutionArtifactInterface $artifact): int => strlen((string) json_encode($artifact->normalize(), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE));
        /** @var int $now */
        $now = ($this->nowSeconds)();
        $this->lastFlushEpoch = $now;
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $item = $this->toItem($artifact);

        if ($this->isFull()) {
            $this->applyOverflow($item);

            return;
        }

        $this->buffer[] = $item;
        $this->estimatedBytes += $item['bytes'];

        /** @var int $now */
        $now = ($this->nowSeconds)();
        if ($this->config->flushIntervalSeconds > 0 && $now - $this->lastFlushEpoch >= $this->config->flushIntervalSeconds) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        $start = hrtime(true);
        /** @var int $now */
        $now = ($this->nowSeconds)();
        $this->lastFlushEpoch = $now;
        $flushed = $this->drain($this->config->batchSize);
        $this->observe($flushed, $start);
    }

    public function close(): void
    {
        $start = hrtime(true);
        $flushed = $this->drain($this->config->shutdownFlushBudget);

        while ($this->buffer !== []) {
            array_shift($this->buffer);
            $this->droppedCount++;
        }

        $this->estimatedBytes = 0;
        $this->observe($flushed, $start);
    }

    public function bufferedCount(): int
    {
        return count($this->buffer);
    }

    public function droppedCount(): int
    {
        return $this->droppedCount;
    }

    public function estimatedBytes(): int
    {
        return $this->estimatedBytes;
    }

    /**
     * @return array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int}
     */
    private function toItem(ExecutionArtifactInterface $artifact): array
    {
        /** @var int $enqueued */
        $enqueued = ($this->nowSeconds)();
        /** @var int $bytes */
        $bytes = ($this->byteEstimator)($artifact);

        return [
            'artifact' => $artifact,
            'enqueued' => $enqueued,
            'bytes' => $bytes,
            'priority' => $this->priorityOf($artifact),
        ];
    }

    private function priorityOf(ExecutionArtifactInterface $artifact): int
    {
        if ($artifact instanceof ObservationInterface && $artifact->severity()->isError()) {
            return 1;
        }

        if ($artifact instanceof SnapshotInterface && $artifact->classification()->isTerminal()) {
            return 1;
        }

        return 0;
    }

    private function isFull(): bool
    {
        return count($this->buffer) >= $this->config->maxItems
            || $this->estimatedBytes >= $this->config->maxEstimatedBytes;
    }

    /**
     * @param array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $incoming
     */
    private function applyOverflow(array $incoming): void
    {
        match ($this->config->overflow) {
            OverflowStrategy::Fail => $this->failOverflow($incoming),
            OverflowStrategy::DropNewest => $this->reportDrop($incoming['artifact']),
            OverflowStrategy::DropOldest => $this->dropOldestThenAppend($incoming),
            OverflowStrategy::DropLowPriority => $this->dropLowPriorityThenAppend($incoming),
            OverflowStrategy::BlockWithTimeout => $this->blockWithTimeoutThenAppend($incoming),
        };
    }

    /**
     * @param array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $incoming
     */
    private function appendIncoming(array $incoming): void
    {
        $this->buffer[] = $incoming;
        $this->estimatedBytes += $incoming['bytes'];
    }

    /**
     * @param array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $incoming
     */
    private function failOverflow(array $incoming): void
    {
        $this->reportDrop($incoming['artifact']);

        throw new BufferOverflowException('Artifact buffer is full.');
    }

    /**
     * @param array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $incoming
     */
    private function dropOldestThenAppend(array $incoming): void
    {
        $this->dropOldest();
        $this->appendIncoming($incoming);
    }

    /**
     * @param array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $incoming
     */
    private function dropLowPriorityThenAppend(array $incoming): void
    {
        if ($incoming['priority'] === 0) {
            $this->reportDrop($incoming['artifact']);

            return;
        }

        $index = $this->oldestLowPriorityIndex();

        if ($index !== null) {
            $this->removeAt($index);
        } else {
            $this->dropOldest();
        }

        $this->appendIncoming($incoming);
    }

    /**
     * @param array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $incoming
     */
    private function blockWithTimeoutThenAppend(array $incoming): void
    {
        $deadline = hrtime(true) + self::BLOCK_BUDGET_MICROSECONDS * 1000;

        while ($this->isFull() && hrtime(true) < $deadline) {
            usleep(100);
        }

        if ($this->isFull()) {
            $this->reportDrop($incoming['artifact']);

            return;
        }

        $this->appendIncoming($incoming);
    }

    private function dropOldest(): void
    {
        /** @var array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int}|null $item */
        $item = array_shift($this->buffer);

        if ($item !== null) {
            $this->estimatedBytes -= $item['bytes'];
            $this->reportDrop($item['artifact']);
        }
    }

    private function removeAt(int $index): void
    {
        /** @var array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $item */
        $item = $this->buffer[$index];
        unset($this->buffer[$index]);
        $this->buffer = array_values($this->buffer);
        $this->estimatedBytes -= $item['bytes'];
        $this->reportDrop($item['artifact']);
    }

    /**
     * @return int|null
     */
    private function oldestLowPriorityIndex(): ?int
    {
        foreach ($this->buffer as $index => $item) {
            if ($item['priority'] === 0) {
                return $index;
            }
        }

        return null;
    }

    private function drain(int $limit): int
    {
        $flushed = 0;

        while ($this->buffer !== [] && $flushed < $limit) {
            /** @var array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $item */
            $item = array_shift($this->buffer);
            $this->estimatedBytes -= $item['bytes'];

            /** @var int $enqueued */
            $enqueued = $item['enqueued'];
            /** @var int $now */
            $now = ($this->nowSeconds)();

            if ($now - $enqueued > $this->config->maxAgeSeconds) {
                $this->reportDrop($item['artifact']);

                continue;
            }

            $this->inner->publish($item['artifact']);
            $flushed++;
        }

        return $flushed;
    }

    private function observe(int $flushed, int $startedAtNanoseconds): void
    {
        if ($this->observer === null) {
            return;
        }

        $durationMicroseconds = max(0, (int) ((hrtime(true) - $startedAtNanoseconds) / 1000));
        $this->observer->onFlush($flushed, $durationMicroseconds, count($this->buffer));
    }

    private function reportDrop(ExecutionArtifactInterface $artifact): void
    {
        $this->droppedCount++;
        $this->health?->report(new BufferOverflowException('Artifact dropped by buffer overflow.'), $artifact);
    }
}
