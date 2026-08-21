<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract\Exception;

/**
 * Thrown when an operation is blocked by an uncommitted (dirty) working tree.
 *
 * @api
 */
interface DirtyWorkingTreeException extends VcsOperationException
{
}
