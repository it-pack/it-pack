<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Security;

use ItPack\RunIt\Contract\Application\Security\SnapshotIntegrityVerifierInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;

/**
 * Verifies the integrity invariants of snapshots.
 *
 * Two concerns are covered:
 *
 *  - tamper detection: the stored content hash must match a freshly computed hash
 *    of the snapshot's canonical payload; any mutation of the payload invalidates it;
 *  - predecessor chaining: when a snapshot references a predecessor, that predecessor
 *    must exist and must have been created no later than the snapshot that follows it.
 *
 * Hashing proves integrity, not authenticity — see {@see \ItPack\RunIt\Contract\Port\SnapshotSignerInterface}
 * for the optional signing port. The verifier throws {@see SnapshotIntegrityException} on any
 * violation so callers can reject tampered or mis-chained state before trusting it.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class SnapshotIntegrityVerifier implements SnapshotIntegrityVerifierInterface
{
    public function __construct(
        private readonly HashCalculatorInterface $hashCalculator,
    ) {
    }

    /**
     * @throws SnapshotIntegrityException when the content hash does not match the payload
     */
    public function verifyIntegrity(SnapshotInterface $snapshot): void
    {
        $content = (string) json_encode(
            $snapshot->payload(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $expected = $this->hashCalculator->calculate($content);

        if (!hash_equals($expected, $snapshot->contentHash())) {
            throw SnapshotIntegrityException::tampered($snapshot->id()->toString());
        }
    }

    /**
     * @throws SnapshotIntegrityException when the chain is inconsistent
     */
    public function verifyChain(SnapshotInterface $snapshot, ?SnapshotInterface $predecessor): void
    {
        $predecessorId = $snapshot->predecessorId();

        if ($predecessorId === null) {
            if ($predecessor !== null) {
                throw SnapshotIntegrityException::unexpectedPredecessor($snapshot->id()->toString());
            }

            return;
        }

        if ($predecessor === null) {
            throw SnapshotIntegrityException::missingPredecessor(
                $snapshot->id()->toString(),
                $predecessorId->toString()
            );
        }

        if (!$predecessor->id()->equals($predecessorId)) {
            throw SnapshotIntegrityException::predecessorMismatch(
                $snapshot->id()->toString(),
                $predecessorId->toString(),
                $predecessor->id()->toString(),
            );
        }

        if ($predecessor->timestamp() > $snapshot->timestamp()) {
            throw SnapshotIntegrityException::predecessorOrder(
                $snapshot->id()->toString(),
                $predecessor->id()->toString()
            );
        }
    }
}
