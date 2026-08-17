<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the classification of a snapshot.
 *
 * A checkpoint snapshot is an intermediate, resumable state capture; a terminal
 * snapshot marks the end of an execution and may be retained differently.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface SnapshotClassificationInterface
{
    public function name(): string;

    public function isTerminal(): bool;
}
