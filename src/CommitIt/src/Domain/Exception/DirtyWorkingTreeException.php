<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Exception;

use ItPack\CommitIt\Contract\Exception\DirtyWorkingTreeException as DirtyWorkingTreeExceptionMarker;
use RuntimeException;

/**
 * Thrown when an operation is blocked by an uncommitted (dirty) working tree.
 *
 * @api
 */
final class DirtyWorkingTreeException extends RuntimeException implements DirtyWorkingTreeExceptionMarker
{
}
