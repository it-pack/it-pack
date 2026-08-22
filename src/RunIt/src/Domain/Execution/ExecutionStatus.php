<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Behavior\Domain\TerminalStatusBehavior;
use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;

/**
 * The closed set of execution lifecycle statuses.
 *
 * Terminal statuses (`succeeded`, `failed`, `cancelled`, `timed_out`) cannot be
 * left once entered. The runtime transition matrix is enforced in a later
 * milestone; this enum only describes the status and its terminality.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
enum ExecutionStatus: string implements ExecutionStatusInterface
{
    use TerminalStatusBehavior;

    case Created = 'created';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';

    public function label(): string
    {
        return $this->value;
    }
}
