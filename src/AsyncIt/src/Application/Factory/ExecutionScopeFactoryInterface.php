<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use Stringable;

/**
 * Creates the per-resume RunIt execution scope handed to the context storage.
 *
 * Lives in `Factory` so the scheduler stays `new`-free. The reference runtime
 * returns a lightweight {@see \ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope};
 * Phase 3 supplies real per-fiber RunIt scopes behind the same contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ExecutionScopeFactoryInterface
{
    public function create(string|Stringable $discriminant): ExecutionScopeInterface;
}
