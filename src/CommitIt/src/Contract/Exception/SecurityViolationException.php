<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract\Exception;

use ItPack\CommitIt\Common\CommitItException;

/**
 * Marker for content that contains detected secrets or credentials.
 *
 * @api
 */
interface SecurityViolationException extends CommitItException
{
}
