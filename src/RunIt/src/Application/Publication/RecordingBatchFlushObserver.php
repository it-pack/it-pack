<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\BatchFlushObserverInterface;

/**
 * Counters-only observer for buffered batch flushes.
 *
 * It accumulates flush statistics without performing any I/O or re-publishing
 * artifacts, so wiring it into {@see BufferedArtifactPublisher} can never recurse
 * through the guarded publication pipeline. Dogfooding reads these counters to
 * represent RunIt's own exporter batches as measurements inside a dogfood execution.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RecordingBatchFlushObserver implements BatchFlushObserverInterface
{
    private int $flushCount = 0;

    private int $totalFlushed = 0;

    private int $maxBatch = 0;

    private int $lastFlushMicroseconds = 0;

    private int $lastRemainingBuffered = 0;

    public function onFlush(int $flushedCount, int $durationMicroseconds, int $remainingBuffered): void
    {
        $this->flushCount++;
        $this->totalFlushed += $flushedCount;
        $this->maxBatch = max($this->maxBatch, $flushedCount);
        $this->lastFlushMicroseconds = $durationMicroseconds;
        $this->lastRemainingBuffered = $remainingBuffered;
    }

    public function flushCount(): int
    {
        return $this->flushCount;
    }

    public function totalFlushed(): int
    {
        return $this->totalFlushed;
    }

    public function maxBatch(): int
    {
        return $this->maxBatch;
    }

    public function lastFlushMicroseconds(): int
    {
        return $this->lastFlushMicroseconds;
    }

    public function lastRemainingBuffered(): int
    {
        return $this->lastRemainingBuffered;
    }
}
