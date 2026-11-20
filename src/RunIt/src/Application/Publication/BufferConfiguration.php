<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use InvalidArgumentException;

/**
 * Bounds for the artifact buffer and its flush behavior.
 *
 * Admission does not exceed `maxItems` items or `maxEstimatedBytes` estimated bytes.
 * Estimates are not a bound on actual process memory.
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
        foreach ([
            $maxItems, $maxEstimatedBytes, $maxAgeSeconds, $batchSize, $flushIntervalSeconds, $shutdownFlushBudget,
        ] as $value) {
            if ($value < 0) {
                throw new InvalidArgumentException('Buffer configuration values must not be negative.');
            }
        }
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
