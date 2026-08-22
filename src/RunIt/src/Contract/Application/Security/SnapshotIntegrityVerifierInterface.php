<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application\Security;

use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;

/**
 * Verifies the integrity invariants of snapshots.
 *
 * Covers tamper detection (the stored content hash must match a freshly computed
 * hash of the canonical payload) and predecessor chaining (a referenced predecessor
 * must exist and precede the snapshot in time). The verifier throws on any violation
 * so callers can reject tampered or mis-chained state before trusting it.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface SnapshotIntegrityVerifierInterface
{
    /**
     * @throws RunItExceptionInterface when the content hash does not match the payload
     */
    public function verifyIntegrity(SnapshotInterface $snapshot): void;

    /**
     * @throws RunItExceptionInterface when the chain is inconsistent
     */
    public function verifyChain(SnapshotInterface $snapshot, ?SnapshotInterface $predecessor): void;
}
