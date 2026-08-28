<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Reporting;

use ItPack\CommandIt\Contract\Port\ExecutionReporterInterface;

/**
 * In-memory execution reporter for tests, examples, and disabled emission.
 *
 * It retains every reported entry in insertion order (newest last) so callers
 * can assert on what was emitted. The buffer is bounded by {@see self::$limit};
 * once full, the {@see OverflowPolicy} decides what happens (drop oldest by
 * default, drop newest, or refuse with a {@see BufferOverflowException}).
 *
 * @api
 */
final class CollectingExecutionReporter implements ExecutionReporterInterface
{
    /**
     * Prefix for activity ids synthesized by {@see self::beginActivity()}.
     */
    public const ACTIVITY_ID_PREFIX = 'act-';

    /**
     * @var list<array<string, mixed>>
     */
    private array $entries = [];

    public function __construct(
        private int $limit = 1000,
        private OverflowPolicy $overflow = OverflowPolicy::DropOldest,
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
        $id = \uniqid(self::ACTIVITY_ID_PREFIX, true);
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
        if ($this->limit <= 0) {
            return;
        }

        if (\count($this->entries) >= $this->limit) {
            if ($this->overflow === OverflowPolicy::DropNewest) {
                return;
            }

            if ($this->overflow === OverflowPolicy::Throw) {
                throw BufferOverflowException::forLimit($this->limit);
            }

            \array_shift($this->entries);
        }

        $this->entries[] = $entry;
    }
}
