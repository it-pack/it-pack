<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Exception;

use ItPack\CommitIt\Contract\Exception\SecurityViolationException as SecurityViolationExceptionMarker;
use ItPack\CommitIt\Domain\Value\SecretMatch;
use RuntimeException;

/**
 * Thrown when a secret or credential is detected in content.
 *
 * @api
 */
final class SecurityViolationException extends RuntimeException implements
    SecurityViolationExceptionMarker
{
    /**
     * @param list<SecretMatch> $matches
     */
    public function __construct(
        string $message,
        public readonly array $matches,
    ) {
        parent::__construct($message);
    }
}
