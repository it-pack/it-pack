<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use RuntimeException;

/**
 * Snapshot store that always throws, for exercising storage-failure handling.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class ThrowingSnapshotStore implements SnapshotStoreInterface
{
    public function store(SnapshotInterface $snapshot): void
    {
        throw new RuntimeException('snapshot store is broken');
    }
}
