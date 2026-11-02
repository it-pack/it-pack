<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Scope;

use BadMethodCallException;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * Lightweight RunIt execution scope used by the reference runtime.
 *
 * The AsyncIt reference scheduler only needs a scope object whose *identity*
 * the {@see \ItPack\RunIt\Contract\Port\ContextStorageInterface} can track per
 * resume; it never invokes the scope's recorders. Real per-fiber scopes are
 * supplied by RunIt in Phase 3. Accessors therefore throw — they are not part
 * of the reference scheduling contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
  * @api

  */
final readonly class ReferenceExecutionScope implements ExecutionScopeInterface
{
    public function __construct(public string $discriminant)
    {
    }

    public function execution(): never
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function context(): never
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function activities(): never
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function measurements(): never
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function observations(): never
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function occurrences(): never
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }

    public function snapshots(): never
    {
        throw new BadMethodCallException('ReferenceExecutionScope is a scheduling stand-in.');
    }
}
