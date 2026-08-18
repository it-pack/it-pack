<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Application;

use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionSnapshotInterface;

/**
 * Contract for producing the terminal snapshot of a completed execution.
 *
 * The factory captures the normalized execution state into an immutable, hash-bound
 * terminal snapshot. Identity and hashing are supplied at construction so the
 * factory stays free of randomness and extension-specific hashing.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface ExecutionSnapshotFactoryInterface
{
    public function terminal(ExecutionInterface $execution): ExecutionSnapshotInterface;
}
