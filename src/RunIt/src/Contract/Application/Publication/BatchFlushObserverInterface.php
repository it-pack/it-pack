<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Publication;

/**
 * Observes a buffered publisher's batch flushes for self-instrumentation.
 *
 * This is the single, sanctioned seam where RunIt may observe its own exporter
 * batches: it records how many artifacts a flush emitted and how long the flush
 * took, without ever re-entering the artifact pipeline. An observer is therefore
 * recursion-safe by construction and suitable for the dogfooding bootstrap, where
 * RunIt represents its own tooling runs as executions.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface BatchFlushObserverInterface
{
    /**
     * @param int $flushedCount        artifacts emitted by the flush
     * @param int $durationMicroseconds flush wall time in microseconds (non-negative)
     * @param int $remainingBuffered    artifacts still buffered after the flush
     */
    public function onFlush(int $flushedCount, int $durationMicroseconds, int $remainingBuffered): void;
}
