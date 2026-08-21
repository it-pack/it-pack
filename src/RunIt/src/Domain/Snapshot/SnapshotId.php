<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Snapshot;

use ItPack\RunIt\Behavior\Domain\ValidatedStringIdentifierBehavior;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;

/**
 * The identity of one snapshot.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#snapshotId
 */
final class SnapshotId implements SnapshotIdInterface
{
    use ValidatedStringIdentifierBehavior;
}
