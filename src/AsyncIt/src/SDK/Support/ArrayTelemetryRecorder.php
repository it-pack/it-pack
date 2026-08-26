<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\SDK\Support;

/**
 * In-memory {@see TelemetryRecorderInterface} used as the default recorder.
 *
 * The runtime ships with this lightweight recorder; a production assembly can
 * supply an OpenTelemetry-backed recorder through the lazy bridge factory
 * without changing any consumer code.
 *
 * @see https://w3id.org/itpack/asyncit#RuntimeAdapter
 */
final class ArrayTelemetryRecorder implements TelemetryRecorderInterface
{
    /**
     * @var list<array{event: string, context: array<string, mixed>}>
     */
    private array $events = [];

    public function record(string $event, array $context): void
    {
        $this->events[] = ['event' => $event, 'context' => $context];
    }

    public function events(): array
    {
        return $this->events;
    }
}
