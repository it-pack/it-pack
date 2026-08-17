<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Snapshot;

use ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface;

/**
 * The closed set of snapshot classifications.
 *
 * A `checkpoint` snapshot is an intermediate, resumable capture; a `terminal` snapshot
 * marks the end of an execution.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
enum SnapshotClassification: string implements SnapshotClassificationInterface
{
    case Checkpoint = 'checkpoint';
    case Terminal = 'terminal';

    public function name(): string
    {
        return $this->value;
    }

    public function isTerminal(): bool
    {
        return $this === self::Terminal;
    }
}
