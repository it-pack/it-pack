<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Telemetry;

/**
 * Controls how much of a snapshot payload the bridge exports.
 *
 * Snapshots can carry large normalized state. Exporting the full payload to
 * OpenTelemetry logs is opt-in because it can be large, unbounded, or sensitive.
 * The default is to export only the identity, type, and content hash so a
 * receiver can correlate without ingesting the payload.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
enum SnapshotPayloadPolicy: string
{
    case None = 'none';

    case HashOnly = 'hash_only';

    case Full = 'full';
}
