<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Memory;

use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;

/**
 * In-memory snapshot store for tests, examples, and optional persistence.
 *
 * It retains every stored snapshot in insertion order so callers can assert on what was
 * persisted. It never throws.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class InMemorySnapshotStore implements SnapshotStoreInterface
{
    /**
     * @var list<SnapshotInterface>
     */
    private array $snapshots = [];

    public function store(SnapshotInterface $snapshot): void
    {
        $this->snapshots[] = $snapshot;
    }

    /**
     * @return list<SnapshotInterface>
     */
    public function stored(): array
    {
        return $this->snapshots;
    }
}
