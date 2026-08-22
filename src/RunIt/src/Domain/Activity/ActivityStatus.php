<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Activity;

use ItPack\RunIt\Behavior\Domain\TerminalStatusBehavior;
use ItPack\RunIt\Contract\Domain\ActivityStatusInterface;

/**
 * The closed set of activity lifecycle statuses.
 *
 * Mirrors the execution status set. Terminal statuses (`succeeded`, `failed`,
 * `cancelled`, `timed_out`) cannot be left once entered.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
enum ActivityStatus: string implements ActivityStatusInterface
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
