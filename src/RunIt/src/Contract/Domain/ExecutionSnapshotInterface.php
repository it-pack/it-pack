<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for an execution snapshot.
 *
 * An execution snapshot is a snapshot whose state is the execution itself (definitions,
 * status, context, and artifact summary). It refines `SnapshotInterface` so consumers can
 * treat every snapshot uniformly while distinguishing execution snapshots specifically.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface ExecutionSnapshotInterface extends SnapshotInterface
{
}
