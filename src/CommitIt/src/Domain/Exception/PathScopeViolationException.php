<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Exception;

use InvalidArgumentException;
use ItPack\CommitIt\Contract\Exception\PathScopeViolationException as PathScopeViolationExceptionMarker;

/**
 * Thrown when a path resolves outside the repository working-tree scope.
 *
 * @api
 */
final class PathScopeViolationException extends InvalidArgumentException implements
    PathScopeViolationExceptionMarker
{
}
