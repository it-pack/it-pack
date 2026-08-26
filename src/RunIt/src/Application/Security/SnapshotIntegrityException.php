<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Security;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use RuntimeException;

/**
 * Raised when a snapshot fails an integrity check (tamper detection or predecessor chaining).
 *
 * The message names the snapshot and the nature of the violation so the problem is diagnosable
 * without echoing the payload.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class SnapshotIntegrityException extends RuntimeException implements RunItExceptionInterface
{
    public static function tampered(string $snapshotId): self
    {
        return new self(sprintf(
            'Snapshot "%s" content hash does not match its payload (tamper detected).',
            $snapshotId
        ));
    }

    public static function missingPredecessor(string $snapshotId, string $predecessorId): self
    {
        return new self(sprintf('Snapshot "%s" references missing predecessor "%s".', $snapshotId, $predecessorId));
    }

    public static function unexpectedPredecessor(string $snapshotId): self
    {
        return new self(sprintf('Snapshot "%s" has no predecessor reference but one was supplied.', $snapshotId));
    }

    public static function predecessorMismatch(string $snapshotId, string $expected, string $actual): self
    {
        return new self(sprintf(
            'Snapshot "%s" predecessor mismatch: expected "%s", got "%s".',
            $snapshotId,
            $expected,
            $actual
        ));
    }

    public static function predecessorOrder(string $snapshotId, string $predecessorId): self
    {
        return new self(sprintf('Snapshot "%s" is older than its predecessor "%s".', $snapshotId, $predecessorId));
    }
}
