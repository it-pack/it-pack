<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract\Exception;

use ItPack\CommitIt\Common\CommitItException;

/**
 * Marker for paths that attempt to escape the repository working-tree scope.
 *
 * @api
 */
interface PathScopeViolationException extends CommitItException
{
}
