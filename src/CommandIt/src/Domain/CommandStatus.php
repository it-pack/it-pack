<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Domain;

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
    case Defined = 'defined';
    case Received = 'received';
    case Executing = 'executing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return $this->value;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Cancelled => true,
            default => false,
        };
    }
}
