<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Exception;

use InvalidArgumentException;
use ItPack\CommitIt\Contract\Exception\InvalidReferenceNameException as InvalidReferenceNameExceptionMarker;

/**
 * Thrown when a branch, tag, or remote name fails validation before the backend
 * is invoked.
 *
 * @api
 */
final class InvalidReferenceNameException extends InvalidArgumentException implements InvalidReferenceNameExceptionMarker
{
}
