<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Exception;

use InvalidArgumentException;
use ItPack\CommitIt\Contract\Exception\InvalidSparsePatternException as InvalidSparsePatternExceptionMarker;

/**
 * Thrown when a sparse-checkout pattern fails validation before the backend
 * is invoked.
 *
 * @api
 */
final class InvalidSparsePatternException extends InvalidArgumentException implements
    InvalidSparsePatternExceptionMarker
{
}
