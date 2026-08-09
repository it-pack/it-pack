<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the identifier of a snapshot.
 *
 * A snapshot identifier is unique to exactly one immutable snapshot. Snapshot
 * chains reference their predecessor through this identity, so a store can
 * resolve a chain without retaining the payloads.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#snapshotId
 */
interface SnapshotIdInterface extends StringIdentifierInterface
{
}
