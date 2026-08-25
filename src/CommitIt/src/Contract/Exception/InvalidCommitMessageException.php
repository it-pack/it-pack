<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract\Exception;

/**
 * Thrown when a commit message fails validation before a commit is created.
 *
 * @api
 */
interface InvalidCommitMessageException extends ValidationException
{
}
