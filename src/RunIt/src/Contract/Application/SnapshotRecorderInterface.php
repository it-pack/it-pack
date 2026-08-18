<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;

/**
 * Contract for recording snapshots bound to the ambient execution (and activity).
 *
 * The recorder mints identity, calculates the content hash, fills ownership,
 * timing, and sequence automatically, and publishes the snapshot before returning it.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface SnapshotRecorderInterface
{
    /**
     * @param mixed $payload
     */
    public function record(
        SnapshotTypeInterface $type,
        mixed $payload,
        ?SnapshotClassificationInterface $classification = null,
        int $schemaVersion = 1,
        ?AttributeCollectionInterface $attributes = null,
    ): SnapshotInterface;
}
