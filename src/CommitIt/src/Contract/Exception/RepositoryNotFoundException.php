<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract\Exception;

/**
 * Thrown when a repository path does not contain a valid VCS repository.
 *
 * @api
 */
interface RepositoryNotFoundException extends VcsOperationException
{
}
