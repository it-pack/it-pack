<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use RuntimeException;
use Throwable;

/**
 * Thrown by {@see \ItPack\AsyncIt\Domain\CancellationScope::throwIfCancelled()}
 * when the scope has been cancelled.
 *
 * Cancellation is observed, never preemptive: a running task checks and unwinds
 * gracefully rather than being forcibly killed. The exception carries the
 * structured reason the scope was cancelled.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 * @see https://w3id.org/itpack/asyncit#CancellationReason
 */
final class CancellationException extends RuntimeException implements AsyncItExceptionInterface
{
    public function __construct(
        private readonly CancellationReason $reason,
        string $message = 'Task was cancelled.',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function reason(): CancellationReason
    {
        return $this->reason;
    }
}
