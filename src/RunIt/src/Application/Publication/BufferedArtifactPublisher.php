<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use Closure;
use ItPack\RunIt\Contract\Application\Publication\BatchFlushObserverInterface;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ArtifactByteEstimatorInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use Throwable;

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
 * All time — wall-clock bookkeeping, monotonic self-timing, and blocking deadlines —
 * flows through the {@see MonotonicClockInterface} port, and the overflow wait goes
 * through an injectable sleep seam, so overflow behavior is deterministic under test.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class BufferedArtifactPublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    private const string KEY_ATTRIBUTE = 'artifact';

    private const string KEY_ENQUEUED = 'enqueued';

    private const string KEY_BYTES = 'bytes';

    private const string KEY_PRIORITY = 'priority';

    private const int BLOCK_BUDGET_MICROSECONDS = 2000;

    /**
     * Priority tiers for the DropLowPriority overflow strategy: high-priority
     * artifacts are error observations and terminal snapshots.
     */
    private const int PRIORITY_HIGH = 1;

    private const int PRIORITY_LOW = 0;

    private const int SLEEP_QUANTUM_MICROSECONDS = 100;

    /**
     * @var array<int, array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int}>
     */
    private array $buffer = [];

    private int $estimatedBytes = 0;

    private int $droppedCount = 0;

    private int $lastFlushEpoch;

    private readonly Closure $sleep;

    /**
     * @param Closure(int $microseconds): void|null $sleep injectable wait for the blocking overflow strategy
     */
    public function __construct(
        private readonly BufferConfiguration $config,
        private readonly ArtifactPublisherInterface $inner,
        private readonly MonotonicClockInterface $clock,
        private readonly ArtifactByteEstimatorInterface $byteEstimator,
        private readonly ?InternalHealthReporterInterface $health = null,
        private readonly ?BatchFlushObserverInterface $observer = null,
        ?Closure $sleep = null,
    ) {
        $this->sleep = $sleep ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
        $now = $this->clock->nowSeconds();
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
        $this->estimatedBytes += $item[self::KEY_BYTES];

        $now = $this->clock->nowSeconds();
        if ($this->config->flushIntervalSeconds > 0
            && $now - $this->lastFlushEpoch >= $this->config->flushIntervalSeconds
        ) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        $start = $this->clock->monotonicNanoseconds();
        $this->lastFlushEpoch = $this->clock->nowSeconds();
        $flushed = $this->drain($this->config->batchSize);
        $this->observe($flushed, $start);
    }

    public function close(): void
    {
        $start = $this->clock->monotonicNanoseconds();
        $flushed = $this->drain($this->config->shutdownFlushBudget);

        while ($this->buffer !== []) {
            /** @var array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $item */
            $item = array_shift($this->buffer);
            $this->estimatedBytes -= $item[self::KEY_BYTES];
            $this->reportDrop($item[self::KEY_ATTRIBUTE]);
        }

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
        $enqueued = $this->clock->nowSeconds();
        $bytes = $this->byteEstimator->estimate($artifact);

        return [
            self::KEY_ATTRIBUTE => $artifact,
            self::KEY_ENQUEUED => $enqueued,
            self::KEY_BYTES => $bytes,
            self::KEY_PRIORITY => $this->priorityOf($artifact),
        ];
    }

    private function priorityOf(ExecutionArtifactInterface $artifact): int
    {
        if ($artifact instanceof ObservationInterface && $artifact->severity()->isError()) {
            return self::PRIORITY_HIGH;
        }

        if ($artifact instanceof SnapshotInterface && $artifact->classification()->isTerminal()) {
            return self::PRIORITY_HIGH;
        }

        return self::PRIORITY_LOW;
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
            OverflowStrategy::DropNewest => $this->reportDrop($incoming[self::KEY_ATTRIBUTE]),
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
        $this->estimatedBytes += $incoming[self::KEY_BYTES];
    }

    /**
     * @param array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $incoming
     */
    private function failOverflow(array $incoming): never
    {
        $this->reportDrop($incoming[self::KEY_ATTRIBUTE]);

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
        if ($incoming[self::KEY_PRIORITY] === self::PRIORITY_LOW) {
            $this->reportDrop($incoming[self::KEY_ATTRIBUTE]);

            return;
        }

        $index = $this->oldestLowPriorityIndex();

        if ($index !== null) {
            $this->removeAt($index);
            $this->appendIncoming($incoming);

            return;
        }

        $this->dropOldest();
        $this->appendIncoming($incoming);
    }

    /**
     * @param array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $incoming
     */
    private function blockWithTimeoutThenAppend(array $incoming): void
    {
        $deadline = $this->clock->monotonicNanoseconds()
            + self::BLOCK_BUDGET_MICROSECONDS * Duration::NANOS_PER_MICROSECOND;

        while ($this->isFull() && $this->clock->monotonicNanoseconds() < $deadline) {
            ($this->sleep)(self::SLEEP_QUANTUM_MICROSECONDS);
        }

        if ($this->isFull()) {
            $this->reportDrop($incoming[self::KEY_ATTRIBUTE]);

            return;
        }

        $this->appendIncoming($incoming);
    }

    private function dropOldest(): void
    {
        /** @var array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int}|null $item */
        $item = array_shift($this->buffer);

        if ($item !== null) {
            $this->estimatedBytes -= $item[self::KEY_BYTES];
            $this->reportDrop($item[self::KEY_ATTRIBUTE]);
        }
    }

    private function removeAt(int $index): void
    {
        /** @var array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $item */
        $item = $this->buffer[$index];
        unset($this->buffer[$index]);
        $this->buffer = array_values($this->buffer);
        $this->estimatedBytes -= $item[self::KEY_BYTES];
        $this->reportDrop($item[self::KEY_ATTRIBUTE]);
    }

    private function oldestLowPriorityIndex(): ?int
    {
        return array_find_key($this->buffer, fn ($item): bool => $item[self::KEY_PRIORITY] === self::PRIORITY_LOW);

    }

    /**
     * Flushes up to `$limit` artifacts to the inner publisher.
     *
     * Sink faults from the inner publisher propagate to the caller unchanged:
     * when a `FailSafeArtifactPublisher` sits above (the default wiring), it
     * owns the failure policy; standalone use therefore surfaces sink faults
     * loudly rather than silently dropping them. Expired artifacts are dropped
     * and reported through the internal health reporter.
     *
     * @throws Throwable when the inner publisher throws
     */
    private function drain(int $limit): int
    {
        $flushed = 0;

        while ($this->buffer !== [] && $flushed < $limit) {
            /** @var array{artifact: ExecutionArtifactInterface, enqueued: int, bytes: int, priority: int} $item */
            $item = array_shift($this->buffer);
            $this->estimatedBytes -= $item[self::KEY_BYTES];

            /** @var int $enqueued */
            $enqueued = $item[self::KEY_ENQUEUED];

            if ($this->clock->nowSeconds() - $enqueued > $this->config->maxAgeSeconds) {
                $this->reportDrop($item[self::KEY_ATTRIBUTE]);

                continue;
            }

            $this->inner->publish($item[self::KEY_ATTRIBUTE]);
            $flushed++;
        }

        return $flushed;
    }

    private function observe(int $flushed, int $startedAtNanoseconds): void
    {
        if (!$this->observer instanceof BatchFlushObserverInterface) {
            return;
        }

        $durationMicroseconds = max(
            0,
            (int) (($this->clock->monotonicNanoseconds() - $startedAtNanoseconds) / Duration::NANOS_PER_MICROSECOND)
        );
        $this->observer->onFlush($flushed, $durationMicroseconds, count($this->buffer));
    }

    private function reportDrop(ExecutionArtifactInterface $artifact): void
    {
        $this->droppedCount++;
        $this->health?->report(new BufferOverflowException('Artifact dropped by buffer overflow.'), $artifact);
    }
}
