<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

/**
 * Contract for a publisher that batches artifacts and must be flushed.
 *
 * Buffered, queue-backed, and OpenTelemetry publishers accumulate artifacts and
 * emit them on a flush boundary. Callers flush at an explicit boundary (for
 * example a worker shutdown or a request end) rather than on every publish.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface FlushablePublisherInterface
{
    /**
     * Emits any buffered artifacts.
     */
    public function flush(): void;
}
