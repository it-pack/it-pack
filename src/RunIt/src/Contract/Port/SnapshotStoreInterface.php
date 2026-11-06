<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Port;

use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use Throwable;

/**
 * Port that durably persists a snapshot.
 *
 * Snapshot persistence is optional. The runtime records snapshots through the
 * recorder and, when a store is configured, also persists them through this port.
 * A store decides whether persistence means an in-memory buffer, a file, a database,
 * or nothing at all. Adapters must never change the snapshot and must not replace
 * the original business result or exception when persistence fails.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface SnapshotStoreInterface
{
    /**
     * Persists a snapshot.
     *
     * Implementations must be safe to call repeatedly and must not retain the
     * snapshot, only its normalized projection if needed.
     *
     * @throws Throwable implementations may throw; callers decide the failure policy
     */
    public function store(SnapshotInterface $snapshot): void;
}
