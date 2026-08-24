<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use ItPack\RunIt\Contract\Domain\ActivityStatusInterface;
use ItPack\RunIt\Contract\Exception\LifecycleExceptionInterface;
use RuntimeException;

/**
 * Raised when a terminal activity is completed again.
 *
 * A completed activity cannot be reopened. Repeating a completion is treated as
 * a conflicting re-completion rather than an idempotent no-op because activities
 * have no documented idempotent path above the aggregate. The message names the
 * existing terminal status so the conflict is diagnosable.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Activity
 */
final class ConflictingActivityCompletionException extends RuntimeException implements LifecycleExceptionInterface
{
    public static function forConflict(ActivityStatusInterface $existing): self
    {
        return new self(
            sprintf(
                'Activity is already terminal with status "%s"; completing it again is not allowed.',
                $existing->label(),
            ),
        );
    }
}
