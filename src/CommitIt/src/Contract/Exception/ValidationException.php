<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract\Exception;

use ItPack\CommitIt\Common\CommitItException;

/**
 * Marker for failures caused by invalid caller input that is rejected *before*
 * any backend is contacted (e.g. a branch/tag/commit-message that fails domain
 * validation). These are logic-level errors, parallel to — but distinct from —
 * {@see VcsOperationException}, which is reserved for backend/runtime failures.
 *
 * @api
 */
interface ValidationException extends CommitItException
{
}
