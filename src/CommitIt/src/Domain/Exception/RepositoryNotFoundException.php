<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Exception;

use ItPack\CommitIt\Contract\Exception\RepositoryNotFoundException as RepositoryNotFoundExceptionMarker;
use RuntimeException;

/**
 * Thrown when a repository path does not contain a valid VCS repository.
 *
 * @api
 */
final class RepositoryNotFoundException extends RuntimeException implements RepositoryNotFoundExceptionMarker
{
}
