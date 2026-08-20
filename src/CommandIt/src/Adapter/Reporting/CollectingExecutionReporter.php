<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Reporting;

use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;

/**
 * In-memory execution reporter for tests, examples, and disabled emission.
 *
 * It retains every reported entry in insertion order (newest last) so callers
 * can assert on what was emitted. Once the bounded buffer is full, the oldest
 * entry is dropped to keep memory bounded.
 *
 * @api
 */
final class CollectingExecutionReporter implements ExecutionReporterInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $entries = [];

    public function __construct(
        private int $limit = 1000,
    ) {
    }

    public function reportObservation(string $message, array $context = []): void
    {
        $this->record(['type' => 'observation', 'message' => $message, 'context' => $context]);
    }

    public function reportOccurrence(string $type, array $context = []): void
    {
        $this->record(['type' => 'occurrence', 'name' => $type, 'context' => $context]);
    }

    public function reportMeasurement(string $name, int|float $value, string $unit): void
    {
        $this->record(['type' => 'measurement', 'name' => $name, 'value' => $value, 'unit' => $unit]);
    }

    public function beginActivity(string $name): string
    {
        $id = \uniqid('act-', true);
        $this->record(['type' => 'activity_start', 'id' => $id, 'name' => $name]);

        return $id;
    }

    public function endActivity(string $activityId): void
    {
        $this->record(['type' => 'activity_end', 'id' => $activityId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function record(array $entry): void
    {
        if (\count($this->entries) >= $this->limit) {
            \array_shift($this->entries);
        }

        $this->entries[] = $entry;
    }
}
