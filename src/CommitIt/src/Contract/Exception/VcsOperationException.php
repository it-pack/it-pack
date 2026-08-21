<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract\Exception;

use ItPack\CommitIt\Common\CommitItException;

/**
 * Base marker for any failure originating from a VCS operation.
 *
 * @api
 */
interface VcsOperationException extends CommitItException
{
}
