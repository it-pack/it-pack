<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an immutable snapshot artifact.
 *
 * A snapshot captures the normalized state of an execution at a point in time. It is
 * identified by a stable identifier, typed by a snapshot type, and bound to its payload
 * through a content hash. Snapshots form a predecessor chain so a store can resolve history
 * without retaining every payload inline.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface SnapshotInterface extends ExecutionArtifactInterface, ExecutionOwnedInterface, ActivityOwnedInterface, TimestampedInterface, AttributedInterface
{
    public function id(): SnapshotIdInterface;

    public function type(): SnapshotTypeInterface;

    public function classification(): SnapshotClassificationInterface;

    public function schemaVersion(): int;

    /**
     * @return mixed the normalized, recursively-primitive state payload
     */
    public function payload(): mixed;

    public function contentType(): string;

    public function contentHash(): string;

    public function predecessorId(): ?SnapshotIdInterface;
}
