<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK;

use Closure;
use ItPack\RunIt\Contract\Application\CurrentExecutionInterface;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\SDK\Attribute\DefaultExecutedAsMetadataReader;
use ItPack\RunIt\SDK\Attribute\ExecutedAs;
use ItPack\RunIt\SDK\Attribute\ExecutedAsMetadataReaderInterface;
use ItPack\RunIt\SDK\Decorator\ExecutedCallableFactory;
use ItPack\RunIt\SDK\Decorator\ExecutedCallableFactoryInterface;
use ItPack\RunIt\SDK\Decorator\ExecutedCallableInterface;
use ItPack\RunIt\SDK\Execution\MetadataExecutionDefinitionFactory;
use ItPack\RunIt\SDK\Execution\MetadataExecutionDefinitionFactoryInterface;
use Throwable;

/**
 * Default {@see RunItInterface} facade.
 *
 * Delegates execution to the configured runner and ambient access to the current-execution
 * accessor. Callable wrapping resolves an execution definition from the supplied static definition,
 * a factory, or an {@see ExecutedAs} attribute, and never reflects on the hot path.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class DefaultRunIt implements RunItInterface
{
    private ExecutedCallableFactoryInterface $executedCallableFactory;

    private ExecutedAsMetadataReaderInterface $attributeReader;

    public function __construct(
        private ExecutionRunnerInterface   $runner,
        private CurrentExecutionInterface  $currentExecution,
        ?ExecutedAsMetadataReaderInterface $attributeReader = null,
        ?ExecutedCallableFactoryInterface $executedCallableFactory = null,
        private MetadataExecutionDefinitionFactoryInterface $metadataExecutionDefinitionFactory
            = new MetadataExecutionDefinitionFactory(),
    ) {
        $this->attributeReader = $attributeReader ?? new DefaultExecutedAsMetadataReader();
        $this->executedCallableFactory = $executedCallableFactory ?? new ExecutedCallableFactory($runner);
    }

    /**
     * Start an execution for the given definition and run the operation inside it.
     *
     * @throws Throwable
     */
    public function run(ExecutionDefinitionInterface $definition, callable $operation): mixed
    {
        return $this->runner->run($definition, $operation);
    }

    /**
     * Returns the current execution scope, or null when no execution is active.
     */
    public function currentExecution(): ?ExecutionScopeInterface
    {
        return $this->currentExecution->get();
    }

    /**
     * Returns the execution runner backing this facade.
     */
    public function executionRunner(): ExecutionRunnerInterface
    {
        return $this->runner;
    }

    /**
     * Wrap a callable so every invocation runs inside a RunIt execution.
     *
     * The definition may be static, derived from an {@see ExecutedAs} attribute, or produced
     * per invocation by a callable factory. When the definition is null, the attribute is read
     * and its definition is built during wrapping. If no attribute is found, the default callable
     * throws MissingExecutionDefinitionException when invoked.
     */
    public function wrap(
        callable $operation,
        ExecutionDefinitionInterface|callable|null $definition = null
    ): ExecutedCallableInterface {
        return $this->executedCallableFactory->build(
            $operation,
            $this->resolveDefinition(
                $operation,
                $definition,
            ),
            $this->resolveFactory(
                $definition,
            )
        );
    }

    /**
     * Build an execution definition from resolved metadata.
     */
    private function definitionFromMetadata(ExecutedAs $metadata): ExecutionDefinitionInterface
    {
        return $this->metadataExecutionDefinitionFactory->create($metadata);
    }

    /**
     * Resolve a static definition from an attribute, or null when none applies.
     */
    private function resolveDefinition(
        callable $operation,
        ExecutionDefinitionInterface|callable|null $definition = null,
    ): ?ExecutionDefinitionInterface {
        if ($definition instanceof ExecutionDefinitionInterface) {
            return $definition;
        }

        if ($definition === null) {
            $metadata = $this->attributeReader->read($operation);

            if ($metadata instanceof ExecutedAs) {
                return $this->definitionFromMetadata($metadata);
            }
        }

        return null;
    }

    /**
     * Wrap a callable definition into a per-invocation factory closure, or null when static.
     *
     * The returned closure receives the invocation argument array as its single argument.
     */
    private function resolveFactory(
        ExecutionDefinitionInterface|callable|null $definition,
    ): ?Closure {
        return is_callable($definition)
            ? fn (array $args) => $definition($args)
            : null;
    }
}
