<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * Concrete {@see ExecutionScopeFactoryInterface} for the reference runtime.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ReferenceExecutionScopeFactory implements ExecutionScopeFactoryInterface
{
    public function create(string $discriminant): ExecutionScopeInterface
    {
        return new ReferenceExecutionScope($discriminant);
    }
}
