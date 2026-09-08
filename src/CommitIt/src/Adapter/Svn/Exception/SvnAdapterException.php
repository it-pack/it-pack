<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Adapter\Svn\Exception;

use ItPack\CommitIt\Common\CommitItException;
use ItPack\CommitIt\Contract\Exception\VcsOperationException;
use RuntimeException;

/**
 * Thrown when an SVN adapter operation is not supported or maps inconsistently
 * between SVN concepts and CommitIt domain semantics.
 *
 * @api
 */
final class SvnAdapterException extends RuntimeException implements VcsOperationException, CommitItException
{
}
