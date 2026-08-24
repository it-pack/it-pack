<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Snapshot;

use ItPack\RunIt\Contract\Domain\ExecutionSnapshotInterface;

/**
 * The terminal snapshot of a completed execution.
 *
 * It is a {@see Snapshot} specialized by type (`execution.terminal`) and
 * classification (terminal), stamped with {@see self::SCHEMA_VERSION}, carrying
 * the execution's normalized projection as its payload. All construction flows
 * through the inherited {@see Snapshot::create()} factory, whose late-static
 * binding produces this concrete type.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final readonly class ExecutionSnapshot extends Snapshot implements ExecutionSnapshotInterface
{
    /**
     * The schema version stamped onto terminal execution snapshots.
     */
    public const int SCHEMA_VERSION = 1;
}
