<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;
use ItPack\RunIt\Domain\Context\DefaultContextRedactionPolicy;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Shared\ArtifactField;

/**
 * The immutable blueprint of an execution.
 *
 * The definition carries the name, kind, optional parent and linked execution
 * references, and the initial context. It is the input to an execution runner and
 * never changes as the execution runs.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ExecutionDefinition implements ExecutionDefinitionInterface
{
    /**
     * @param list<ExecutionIdInterface> $linkedExecutionIds
     */
    private function __construct(
        private ExecutionNameInterface    $name,
        private ExecutionKindInterface    $kind,
        private ?ExecutionIdInterface     $parentExecutionId,
        private array                     $linkedExecutionIds,
        private ExecutionContextInterface $context,
    ) {
    }

    /**
     * @param list<ExecutionIdInterface> $linkedExecutionIds
     */
    public static function create(
        ExecutionNameInterface $name,
        ExecutionKindInterface $kind,
        ?ExecutionIdInterface $parentExecutionId = null,
        array $linkedExecutionIds = [],
        ?ExecutionContextInterface $context = null,
    ): self {
        return new self(
            $name,
            $kind,
            $parentExecutionId,
            $linkedExecutionIds,
            $context ?? ExecutionContext::empty()
        );
    }

    public function name(): ExecutionNameInterface
    {
        return $this->name;
    }

    public function kind(): ExecutionKindInterface
    {
        return $this->kind;
    }

    public function parentExecutionId(): ?ExecutionIdInterface
    {
        return $this->parentExecutionId;
    }

    /**
     * @return list<ExecutionIdInterface>
     */
    public function linkedExecutionIds(): array
    {
        return $this->linkedExecutionIds;
    }

    public function context(): ExecutionContextInterface
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return [
            'name' => $this->name->toString(),
            'kind' => $this->kind->toString(),
            'parent_execution_id' => $this->parentExecutionId?->toString(),
            'linked_execution_ids' => array_map(
                static fn (ExecutionIdInterface $id): string => $id->toString(),
                $this->linkedExecutionIds
            ),
            ArtifactField::CONTEXT => $this->context->toArray(
                new DefaultContextRedactionPolicy()
            ),
        ];
    }
}
