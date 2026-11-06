<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

interface ExecutionDefinitionFactoryInterface
{
    /**
     * @param ExecutionNameInterface $name
     * @param ExecutionKindInterface $kind
     * @param ?ExecutionIdInterface $parentExecutionId
     * @param list<ExecutionIdInterface> $linkedExecutionIds
     * @param ?ExecutionContextInterface $context
     */
    public function create(
        ExecutionNameInterface $name,
        ExecutionKindInterface $kind,
        ?ExecutionIdInterface $parentExecutionId = null,
        array $linkedExecutionIds = [],
        ?ExecutionContextInterface $context = null,
    ): ExecutionDefinitionInterface;
}
