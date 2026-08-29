<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Exception;

use InvalidArgumentException;
use ItPack\CommitIt\Contract\Exception\InvalidCommitMessageException as InvalidCommitMessageExceptionMarker;

/**
 * Thrown when a commit message fails validation before a commit is created.
 *
 * @api
 */
final class InvalidCommitMessageException extends InvalidArgumentException implements
    InvalidCommitMessageExceptionMarker
{
}
