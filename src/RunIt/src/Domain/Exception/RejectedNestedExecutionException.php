<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use RuntimeException;

/**
 * Raised when a nested execution is rejected by the configured policy.
 *
 * The reject-nested policy throws this when an operation attempts to start a new
 * execution while one is already active. The message names the active execution so
 * the violation is diagnosable.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RejectedNestedExecutionException extends RuntimeException implements RunItExceptionInterface
{
    public static function forExecution(string $executionId): self
    {
        return new self(
            sprintf('Nested execution rejected: execution "%s" is already active.', $executionId),
        );
    }
}
