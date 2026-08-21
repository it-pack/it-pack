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

    /**
     * Emits a visible, non-sensitive "suppressed" log/span-event for a redacted occurrence.
     *
     * A redacted artifact has lost its concrete {@see OccurrenceInterface} identity, so it
     * can no longer be mapped through {@see self::mapOccurrence()}. This carries only safe
     * identity (and marks the signal `runit.redacted`) so the delivery stays observable.
     *
     * @param array<string, mixed> $normalized the redacted occurrence normalization
     */
    public function mapRedactedOccurrence(array $normalized): void;

    /**
     * Emits a visible, non-sensitive "suppressed" log/span-event for a redacted snapshot.
     *
     * @param array<string, mixed> $normalized the redacted snapshot normalization
     */
    public function mapRedactedSnapshot(array $normalized): void;
}
