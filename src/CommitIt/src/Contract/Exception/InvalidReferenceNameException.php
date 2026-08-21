<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract\Exception;

/**
 * Thrown when a branch, tag, or remote name fails validation before the backend
 * is invoked.
 *
 * @api
 */
interface InvalidReferenceNameException extends VcsOperationException
{
}
