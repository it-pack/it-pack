<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK\Support;

/**
 * Sink for lazily-materialised runtime telemetry.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
interface TelemetryRecorderInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function record(string $event, array $context): void;

    /**
     * @return list<array{event: string, context: array<string, mixed>}>
     */
    public function events(): array;
}
