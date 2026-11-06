<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Execution;

use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;
use ItPack\RunIt\SDK\Attribute\ExecutedAsInterface;

interface MetadataExecutionDefinitionFactoryInterface
{
    public function create(ExecutedAsInterface $executedAs): ExecutionDefinitionInterface;
}
