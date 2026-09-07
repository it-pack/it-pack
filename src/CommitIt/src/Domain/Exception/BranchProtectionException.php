<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Exception;

use ItPack\CommitIt\Contract\Exception\BranchProtectionException as BranchProtectionExceptionMarker;
use RuntimeException;

/**
 * Thrown when a branch operation is blocked by a protection policy.
 *
 * @api
 */
final class BranchProtectionException extends RuntimeException implements
    BranchProtectionExceptionMarker
{
}
