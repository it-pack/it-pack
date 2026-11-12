<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Null;

use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;

/**
 * no-op store for tests, examples.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class NullSnapshotStore implements SnapshotStoreInterface
{
    public function store(SnapshotInterface $snapshot): void
    {
    }
}
