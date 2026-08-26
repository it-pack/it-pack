<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use RuntimeException;

/**
 * Thrown by {@see \ItPack\AsyncIt\Domain\CancellationScope::throwIfCancelled()}
 * when the scope has been cancelled.
 *
 * Cancellation is observed, never preemptive: a running task checks and unwinds
 * gracefully rather than being forcibly killed.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 */
final class CancellationException extends RuntimeException implements AsyncItExceptionInterface
{
}
