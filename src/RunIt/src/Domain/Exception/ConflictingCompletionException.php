<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use ItPack\RunIt\Contract\Domain\ExecutionStatusInterface;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Contract\Exception\LifecycleExceptionInterface;
use RuntimeException;

/**
 * Raised when a terminal execution is completed again with a different terminal status.
 *
 * Repeating the same terminal completion is idempotent and must not raise; only a
 * *conflicting* completion (for example `succeeded` after `failed`) is an error. The
 * message names both statuses so the conflict is diagnosable. The aggregate itself
 * raises this for any re-completion of a terminal record; the coordinator raises it
 * above the aggregate only for conflicting repeats, since idempotent repeats are
 * no-ops there.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ConflictingCompletionException extends RuntimeException implements
    InvalidValueExceptionInterface,
    LifecycleExceptionInterface
{
    public static function forConflict(ExecutionStatusInterface $existing, ExecutionStatusInterface $attempted): self
    {
        return new self(
            sprintf(
                'Execution is already terminal with status "%s"; conflicting completion to "%s" is not allowed.',
                $existing->label(),
                $attempted->label(),
            ),
        );
    }
}
