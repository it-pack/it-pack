<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

/**
 * Contract for observing buffered batch flushes.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface RecordingBatchFlushObserverInterface
{
    public function flushCount(): int;

    public function totalFlushed(): int;

    public function maxBatch(): int;

    public function lastFlushMicroseconds(): int;

    public function lastRemainingBuffered(): int;
}
