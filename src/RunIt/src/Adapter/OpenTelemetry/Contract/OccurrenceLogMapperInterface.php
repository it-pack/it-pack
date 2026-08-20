<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Contract;

use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;

/**
 * Maps RunIt occurrences and snapshots to OpenTelemetry span events and logs.
 *
 * Occurrences are immutable facts; the bridge maps them to span events (and
 * optionally logs) while preserving their identity and type. Snapshots export
 * only identity, type, and hash by default, never the full payload.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface OccurrenceLogMapperInterface
{
    public function mapOccurrence(OccurrenceInterface $occurrence): void;

    public function mapSnapshot(SnapshotInterface $snapshot): void;
}
