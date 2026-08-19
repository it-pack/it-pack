<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use RuntimeException;

/**
 * Raised when an execution lifecycle transition is not permitted.
 *
 * Examples: leaving a terminal state, skipping `running`, or transitioning to the
 * same non-terminal status. The message names the offending transition; the original
 * business result or exception is never affected.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class InvalidLifecycleTransitionException extends RuntimeException implements InvalidValueExceptionInterface
{
    public static function forTransition(ExecutionStatusInterface $from, ExecutionStatusInterface $to): self
    {
        return new self(
            sprintf(
                'Execution transition from "%s" to "%s" is not allowed.',
                $from->label(),
                $to->label(),
            ),
        );
    }
}
