<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK;

use Closure;
use ItPack\RunIt\Contract\Application\CurrentExecutionInterface;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Attribute\DefaultExecutedAsMetadataReader;
use ItPack\RunIt\SDK\Attribute\ExecutedAs;
use ItPack\RunIt\SDK\Attribute\ExecutedAsMetadataReaderInterface;
use ItPack\RunIt\SDK\Decorator\ExecutedCallable;
use ItPack\RunIt\SDK\Decorator\ExecutedCallableInterface;

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
final class DefaultRunIt implements RunItInterface
{
    private readonly ExecutedAsMetadataReaderInterface $attributeReader;

    public function __construct(
        private readonly ExecutionRunnerInterface $runner,
        private readonly CurrentExecutionInterface $currentExecution,
        ?ExecutedAsMetadataReaderInterface $attributeReader = null,
    ) {
        $this->attributeReader = $attributeReader ?? new DefaultExecutedAsMetadataReader();
    }

    public function run(ExecutionDefinitionInterface $definition, callable $operation): mixed
    {
        return $this->runner->run($definition, $operation);
    }

    public function currentExecution(): ?ExecutionScopeInterface
    {
        return $this->currentExecution->get();
    }

    public function executionRunner(): ExecutionRunnerInterface
    {
        return $this->runner;
    }

    public function wrap(callable $operation, ExecutionDefinitionInterface|callable|null $definition = null): ExecutedCallableInterface
    {
        $static = null;
        $factory = null;

        if ($definition !== null) {
            if ($definition instanceof ExecutionDefinitionInterface) {
                $static = $definition;
            }

            if (!$definition instanceof ExecutionDefinitionInterface) {
                $factory = Closure::fromCallable($definition);
            }
        }

        if ($definition === null) {
            $metadata = $this->attributeReader->read($operation);

            if ($metadata instanceof ExecutedAs) {
                $static = $this->definitionFromMetadata($metadata);
            }
        }

        return new ExecutedCallable($this->runner, $operation, $static, $factory);
    }

    private function definitionFromMetadata(ExecutedAs $metadata): ExecutionDefinitionInterface
    {
        return ExecutionDefinition::create(
            ExecutionName::fromString($metadata->name),
            ExecutionKind::fromString($metadata->kind),
        );
    }
}
