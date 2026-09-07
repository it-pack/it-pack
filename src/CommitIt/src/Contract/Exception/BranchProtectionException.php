<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract\Exception;

/**
 * Thrown when a branch operation is blocked by a protection policy.
 *
 * @api
 */
interface BranchProtectionException extends VcsOperationException
{
}
