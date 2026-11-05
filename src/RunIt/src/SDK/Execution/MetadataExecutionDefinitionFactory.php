<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Execution;

use ItPack\RunIt\Contract\Domain\ExecutionDefinitionFactoryInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinitionFactory;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Attribute\ExecutedAsInterface;

final readonly class MetadataExecutionDefinitionFactory implements MetadataExecutionDefinitionFactoryInterface
{
    public function __construct(
        private ExecutionDefinitionFactoryInterface $executionDefinitionFactory = new ExecutionDefinitionFactory
    ){}

    public function create(ExecutedAsInterface $executedAs): ExecutionDefinitionInterface
    {
        return $this->executionDefinitionFactory->create(
            ExecutionName::fromString($executedAs->name),
            ExecutionKind::fromString($executedAs->kind),
        );
    }
}
