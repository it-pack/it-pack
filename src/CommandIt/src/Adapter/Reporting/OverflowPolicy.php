<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Reporting;

use ItPack\CommandIt\Behavior\Domain\LabelableBehavior;

/**
 * Defines what happens when the in-memory reporter buffer is full.
 *
 * @api
 */
enum OverflowPolicy: string implements OverflowPolicyInterface
{
    use LabelableBehavior;

    /**
     * Drop the oldest retained entry and append the new one (default). Keeps the
     * most recent activity at the cost of losing history.
     */
    case DropOldest = 'drop_oldest';

    /**
     * Keep the existing buffer intact and silently discard the new entry.
     */
    case DropNewest = 'drop_newest';

    /**
     * Refuse to grow the buffer and throw a {@see BufferOverflowException}.
     */
    case Throw = 'throw';
}
