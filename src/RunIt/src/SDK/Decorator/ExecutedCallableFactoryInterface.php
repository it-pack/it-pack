<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Decorator;

use Closure;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;

interface ExecutedCallableFactoryInterface
{
    public function build(
        callable                      $operation,
        ?ExecutionDefinitionInterface $definition,
        ?Closure                      $definitionFactory = null,
    ): ExecutedCallableInterface;
}
