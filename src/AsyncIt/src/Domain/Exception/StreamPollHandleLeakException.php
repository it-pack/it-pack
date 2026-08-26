<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain\Exception;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;
use RuntimeException;

/**
 * Thrown when a task terminates without releasing every {@see StreamPollHandle}
 * it registered — a resource leak (conformance Assertion C).
 *
 * The exception records the retained handle so the leak can be reported into
 * the conformance result / health path rather than silently lost.
 *
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 */
final class StreamPollHandleLeakException extends RuntimeException implements AsyncItExceptionInterface
{
    public function __construct(public readonly StreamPollHandle $handle)
    {
        parent::__construct(sprintf('StreamPollHandle "%s" was not released before terminal transition.', $handle->id));
    }
}
