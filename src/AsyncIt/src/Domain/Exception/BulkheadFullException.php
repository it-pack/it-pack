<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use RuntimeException;

/**
 * Thrown when {@see \ItPack\AsyncIt\Domain\Bulkhead} is at capacity in
 * non-blocking mode.
 *
 * @see https://w3id.org/itpack/asyncit#Bulkhead
 */
final class BulkheadFullException extends RuntimeException implements AsyncItExceptionInterface
{
    private const string DEFAULT_MESSAGE_TEMPLATE = 'Bulkhead is at capacity (%d).';

    public function __construct(
        private readonly int $capacity,
        string $message = '',
    ) {
        parent::__construct($message !== ''
            ? $message
            : sprintf(self::DEFAULT_MESSAGE_TEMPLATE, $capacity));
    }

    public function capacity(): int
    {
        return $this->capacity;
    }
}
