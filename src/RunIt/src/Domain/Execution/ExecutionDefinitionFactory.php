<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Execution;

use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionFactoryInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;

final class ExecutionDefinitionFactory implements ExecutionDefinitionFactoryInterface
{
    /**
     * @param list<ExecutionIdInterface> $linkedExecutionIds
     */
    public function create(
        ExecutionNameInterface $name,
        ExecutionKindInterface $kind,
        ?ExecutionIdInterface $parentExecutionId = null,
        array $linkedExecutionIds = [],
        ?ExecutionContextInterface $context = null,
    ): ExecutionDefinitionInterface {
        return ExecutionDefinition::create(
            $name,
            $kind,
            $parentExecutionId,
            $linkedExecutionIds,
            $context,
        );
    }
}
