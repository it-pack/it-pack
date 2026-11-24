<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\OpenTelemetry\Logs;

use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;

/**
 * Contract for mapping RunIt occurrences and snapshots to OpenTelemetry signals.
 *
 * @see https://opentelemetry.io/
 */
interface DefaultOccurrenceLogMapperInterface
{
    public function mapOccurrence(OccurrenceInterface $occurrence): void;

    public function mapSnapshot(SnapshotInterface $snapshot): void;

    /**
     * @param array<string, mixed> $normalized
     */
    public function mapRedactedOccurrence(array $normalized): void;

    /**
     * @param array<string, mixed> $normalized
     */
    public function mapRedactedSnapshot(array $normalized): void;

    /**
     * @param array<string, mixed> $normalized
     * @return array<string, mixed>
     */
    public function getAttributes(array $normalized): array;
}
