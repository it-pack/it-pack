<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Adapter\Memory;

use ItPack\RunIt\Contract\Domain\SnapshotInterface;

/**
 * Contract for an in-memory snapshot store that retains stored snapshots.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface InMemorySnapshotStoreInterface
{
    /**
     * @return list<SnapshotInterface>
     */
    public function stored(): array;
}
