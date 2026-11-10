<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Exception;

use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use LogicException;

/**
 * Raised when a context storage is incompatible with the declared concurrency model.
 *
 * A shared synchronous stack (`StackContextStorage`) must not be paired with an
 * engine that declares concurrent execution, because interleaved runs would share
 * and corrupt ambient scope. Consumers must configure runtime-isolated storage
 * (for example `FiberContextStorage` or `CoroutineContextStorage`) instead.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class IncompatibleContextStorageException extends LogicException implements RunItExceptionInterface
{
    public static function sharedStackWithConcurrentEngine(): self
    {
        return new self(
            'Concurrent execution engine requires runtime-isolated context storage; ' .
            'StackContextStorage is shared across the call stack and must not be ' .
            'paired with an engine that declares concurrent execution. Configure ' .
            'FiberContextStorage, CoroutineContextStorage, or another isolated ' .
            'storage with RunItBuilder::withContextStorage().'
        );
    }
}
