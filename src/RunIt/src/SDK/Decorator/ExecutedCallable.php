<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Decorator;

use Closure;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\SDK\Exception\MissingExecutionDefinitionException;

/**
 * Callable decorator that wraps every invocation in a RunIt execution.
 *
 * The definition is either static or produced by a factory from the invocation arguments. The
 * factory runs once per invocation but performs no reflection on the wrapped callable, so the
 * hot path stays reflection-free. The original result and throwable are preserved exactly.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ExecutedCallable implements ExecutedCallableInterface
{
    private readonly Closure $operation;

    private readonly ?Closure $definitionFactory;

    public function __construct(
        private readonly ExecutionRunnerInterface $runner,
        callable $operation,
        private readonly ?ExecutionDefinitionInterface $definition,
        ?Closure $definitionFactory = null,
    ) {
        $this->operation = Closure::fromCallable($operation);
        $this->definitionFactory = $definitionFactory;
    }

    public function __invoke(mixed ...$args): mixed
    {
        $definition = $this->definitionFactory !== null ? ($this->definitionFactory)($args) : $this->definition;

        if ($definition === null) {
            throw new MissingExecutionDefinitionException();
        }

        return $this->runner->run($definition, function (ExecutionScopeInterface $scope) use ($args): mixed {
            unset($scope);

            return ($this->operation)(...$args);
        });
    }
}
