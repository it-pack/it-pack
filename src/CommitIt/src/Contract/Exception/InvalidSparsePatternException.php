<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract\Exception;

/**
 * Thrown when a sparse-checkout pattern fails validation before the backend
 * is invoked.
 *
 * @api
 */
interface InvalidSparsePatternException extends ValidationException
{
}
