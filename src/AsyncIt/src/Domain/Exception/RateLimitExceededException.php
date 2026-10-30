<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use RuntimeException;

/**
 * Thrown when a rate limiter has no tokens available.
 *
 * @see https://w3id.org/itpack/asyncit#RateLimiter
 */
final class RateLimitExceededException extends RuntimeException implements AsyncItExceptionInterface
{
    public function __construct(
        string $message = 'Rate limit exceeded; no tokens available.',
    ) {
        parent::__construct($message);
    }
}
