<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\OpenTelemetry\Metrics;

/**
 * Contract for a bounded cache of OpenTelemetry instruments.
 *
 * @see https://opentelemetry.io/
 */
interface InstrumentCacheInterface
{
    public function has(string $key): bool;

    public function get(string $key): ?object;

    public function put(string $key, object $instrument): void;

    public function size(): int;
}
