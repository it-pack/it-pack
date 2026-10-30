<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Thrown when a task is rejected because the circuit breaker is open.
 *
 * @see https://w3id.org/itpack/asyncit#CircuitBreaker
 */
final class CircuitBreakerOpenException extends RuntimeException implements AsyncItExceptionInterface
{
    public function __construct(
        string $message = 'Circuit breaker is open; execution rejected.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
