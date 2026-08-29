<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

use ItPack\CommandIt\Behavior\Domain\LabelableBehavior;
use ItPack\CommandIt\Contract\Domain\CommandStatusInterface;

/**
 * The closed lifecycle status of a command execution.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
enum CommandStatus: string implements CommandStatusInterface
{
    use LabelableBehavior;

    case Defined = 'defined';
    case Received = 'received';
    case Executing = 'executing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    /**
     * Reserved terminal status. Declared for the closed lifecycle but not yet
     * reachable: the executor has no cancellation path until a `CancellationPort`
     * is introduced. See `docs/architecture/MODEL.md`.
     */
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Cancelled => true,
            default => false,
        };
    }
}
