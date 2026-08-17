<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Activity;

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
    case Created = 'created';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';

    public function name(): string
    {
        return $this->value;
    }

    public function isTerminal(): bool
    {
        return $this === self::Succeeded
            || $this === self::Failed
            || $this === self::Cancelled
            || $this === self::TimedOut;
    }
}
