<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the type of a snapshot.
 *
 * Snapshot types name the shape of a snapshot's payload, for example
 * `configuration.snapshot` or `execution.snapshot`. They are extensible,
 * validated strings rather than a closed enum.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#snapshotType
 */
interface SnapshotTypeInterface extends StringValueInterface
{
}
