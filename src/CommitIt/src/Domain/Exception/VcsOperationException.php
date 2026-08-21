<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Exception;

use ItPack\CommitIt\Contract\Exception\VcsOperationException as VcsOperationExceptionMarker;
use RuntimeException;

/**
 * Concrete base for failures originating from a VCS operation.
 *
 * @api
 */
final class VcsOperationException extends RuntimeException implements VcsOperationExceptionMarker
{
}
