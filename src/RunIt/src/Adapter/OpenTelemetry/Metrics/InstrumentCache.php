<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Metrics;

/**
 * Bounded cache of OpenTelemetry instruments keyed by a stable identity.
 *
 * Instruments are expensive to create and must be reused across recordings, but
 * an unbounded cache would turn arbitrary measurement names into an unbounded
 * retention path. The cache bounds its size with a simple FIFO eviction: because
 * the OpenTelemetry meter also caches instruments by name, recreating an evicted
 * instrument returns the same underlying instrument, so eviction never changes
 * emitted data.
 *
 * @see https://w3id.org/itpack/runit#Measurement
 */
final class InstrumentCache
{
    /** @var array<string, object> */
    private array $cache = [];

    public function __construct(private readonly int $maxSize)
    {
    }

    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    public function get(string $key): ?object
    {
        return $this->cache[$key] ?? null;
    }

    public function put(string $key, object $instrument): void
    {
        if (!isset($this->cache[$key]) && count($this->cache) >= $this->maxSize) {
            array_shift($this->cache);
        }

        $this->cache[$key] = $instrument;
    }

    public function size(): int
    {
        return count($this->cache);
    }
}
