<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use ItPack\RunIt\Contract\Domain\ActivityStatusInterface;
use ItPack\RunIt\Contract\Exception\LifecycleExceptionInterface;
use RuntimeException;

/**
 * Raised when an activity lifecycle transition is not permitted.
 *
 * Example: completing a running activity to a non-terminal status, which would
 * produce an ended record that is neither open nor complete. The message names
 * the offending transition; the original business result or exception is never
 * affected.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final class InvalidActivityTransitionException extends RuntimeException implements LifecycleExceptionInterface
{
    public static function forIllegalTarget(ActivityStatusInterface $from, ActivityStatusInterface $to): self
    {
        return new self(
            sprintf(
                'Activity transition from "%s" to "%s" is not allowed.',
                $from->label(),
                $to->label(),
            ),
        );
    }
}
