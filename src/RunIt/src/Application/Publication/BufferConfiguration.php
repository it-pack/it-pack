<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

/**
 * Bounds for the artifact buffer and its flush behavior.
 *
 * Every bound is a hard limit: the buffer never grows past `maxItems` items or
 * `maxEstimatedBytes` estimated bytes, so memory stays bounded under sustained load.
 * `maxAgeSeconds` drops stale artifacts on flush; `batchSize` caps how many artifacts a
 * single flush emits; `flushIntervalSeconds` is advisory for a caller-driven periodic
 * flush loop; `shutdownFlushBudget` caps how many artifacts `close()` will attempt to
 * flush before it drops the remainder.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class BufferConfiguration
{
    public function __construct(
        public int $maxItems,
        public int $maxEstimatedBytes,
        public int $maxAgeSeconds,
        public int $batchSize,
        public int $flushIntervalSeconds,
        public int $shutdownFlushBudget,
        public OverflowStrategy $overflow,
    ) {
    }

    /**
     * A small, fail-open buffer used as a safe default.
     */
    public static function default(): self
    {
        return new self(
            maxItems: 1024,
            maxEstimatedBytes: 8 * 1024 * 1024,
            maxAgeSeconds: 60,
            batchSize: 256,
            flushIntervalSeconds: 5,
            shutdownFlushBudget: 4096,
            overflow: OverflowStrategy::DropOldest,
        );
    }
}
