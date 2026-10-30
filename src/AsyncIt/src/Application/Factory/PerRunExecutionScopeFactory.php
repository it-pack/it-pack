<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Factory;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;

/**
 * Scope factory that yields exactly one caller-supplied RunIt scope.
 *
 * The engine binds the ambient context for a single
 * {@see \ItPack\RunIt\Contract\Application\ExecutionEngineInterface::run()} call;
 * the discriminant is ignored because the scope identity is fixed for the run.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class PerRunExecutionScopeFactory implements ExecutionScopeFactoryInterface
{
    public function __construct(private ExecutionScopeInterface $scope)
    {
    }

    public function create(string $discriminant): ExecutionScopeInterface
    {
        return $this->scope;
    }
}
