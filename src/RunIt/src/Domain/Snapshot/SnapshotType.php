<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Snapshot;

use ItPack\RunIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;

/**
 * The type of a snapshot, for example `configuration.snapshot` or
 * `execution.snapshot`.
 *
 * Snapshot types name the shape of a snapshot's payload. They are extensible,
 * validated strings rather than a closed enum. RunIt's own canonical types are
 * exposed as named constructors so the spelling lives in exactly one place.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#snapshotType
 */
final readonly class SnapshotType implements SnapshotTypeInterface
{
    use ValidatedStringValueBehavior;

    /**
     * The terminal snapshot the runner emits when an execution completes.
     */
    public static function executionTerminal(): self
    {
        return self::fromString('execution.terminal');
    }
}
