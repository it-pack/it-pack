<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract\Exception;

/**
 * Thrown when a worktree operation is denied by an access-control policy.
 *
 * @api
 */
interface WorktreeAccessDeniedException extends VcsOperationException
{
}
