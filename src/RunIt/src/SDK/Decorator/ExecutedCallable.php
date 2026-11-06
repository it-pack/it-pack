<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Decorator;

use Closure;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\SDK\Exception\MissingExecutionDefinitionException;
use Throwable;

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
final readonly class ExecutedCallable implements ExecutedCallableInterface
{
    private Closure $operation;

    public function __construct(
        private ExecutionRunnerInterface      $runner,
        callable                              $operation,
        private ?ExecutionDefinitionInterface $definition,
        private ?Closure                              $definitionFactory = null,
    ) {
        $this->operation = $operation(...);
    }

    /**
     * @throws Throwable
     */
    public function __invoke(mixed ...$args): mixed
    {
        $definition = $this->definitionFactory instanceof Closure
            ? ($this->definitionFactory)($args) :
            $this->definition;

        if ($definition === null) {
            throw new MissingExecutionDefinitionException(
                'No executed callable definition is defined for this operation.'
            );
        }

        return $this->runner->run($definition, function (ExecutionScopeInterface $scope) use ($args): mixed {
            /** @todo This is probably noop */
            unset($scope);

            return ($this->operation)(...$args);
        });
    }
}
