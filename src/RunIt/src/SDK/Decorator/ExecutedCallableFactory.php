<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Decorator;

use Closure;
use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\Contract\Domain\ExecutionDefinitionInterface;

final readonly class ExecutedCallableFactory implements ExecutedCallableFactoryInterface
{
    public function __construct(
        private ExecutionRunnerInterface $runner,
    ) {
    }

    public function build(
        callable                      $operation,
        ?ExecutionDefinitionInterface $definition,
        ?Closure                      $definitionFactory = null,
    ): ExecutedCallableInterface {
        return new ExecutedCallable(
            $this->runner,
            $operation,
            $definition,
            $definitionFactory,
        );
    }
}
