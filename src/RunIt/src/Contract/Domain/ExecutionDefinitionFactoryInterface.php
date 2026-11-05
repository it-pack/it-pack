<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

interface ExecutionDefinitionFactoryInterface
{
    public function create(
        ExecutionNameInterface $name,
        ExecutionKindInterface $kind,
        ?ExecutionIdInterface $parentExecutionId = null,
        array $linkedExecutionIds = [],
        ?ExecutionContextInterface $context = null,
    ): ExecutionDefinitionInterface;
}
