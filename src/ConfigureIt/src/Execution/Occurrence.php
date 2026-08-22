<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Execution;

use ItPack\ConfigureIt\Contract\Execution\OccurrenceInterface;
use ItPack\ConfigureIt\ValueObject\OccurrenceType;

/**
 * An immutable fact that happened during configuration resolution.
 *
 * Occurrences answer "what happened?" (ConfigurationLoaded, Merged, Validated,
 * Resolved, SnapshotCreated). Mirrors docs/EXECUTION.md "Occurrence"; CloudEvents
 * are one possible external representation (an adapter concern).
 */
final readonly class Occurrence implements OccurrenceInterface
{
    /**
     * @param array<string, mixed> $payload structured detail about the occurrence.
     */
    public function __construct(
        private OccurrenceType $type,
        private float $at,
        private array $payload = [],
    ) {
    }

    public function type(): string
    {
        return $this->type->value;
    }

    public function at(): float
    {
        return $this->at;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}
