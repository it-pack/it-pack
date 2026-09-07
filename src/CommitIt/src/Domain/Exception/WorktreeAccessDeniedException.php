<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Exception;

use ItPack\CommitIt\Contract\Exception\WorktreeAccessDeniedException as WorktreeAccessDeniedExceptionMarker;
use RuntimeException;

/**
 * Thrown when a worktree operation is denied by an access-control policy.
 *
 * @api
 */
final class WorktreeAccessDeniedException extends RuntimeException implements
    WorktreeAccessDeniedExceptionMarker
{
}
