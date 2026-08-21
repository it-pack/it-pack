<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;

/**
 * A processor that needs the active {@see ExecutionContextInterface} to do its work.
 *
 * The builder injects the configured execution context into any processor
 * implementing this interface at {@see \ItPack\ConfigureIt\Core\ConfigurationBuilder::build()}
 * time, so processors stay stateless/immutable while still being context-aware
 * (e.g. conditional includes gated on env/tenant/profile).
 */
interface ExecutionContextAwareProcessorInterface extends ConfigurationProcessorInterface
{
    /**
     * Return a copy of this processor bound to the given execution context.
     *
     * @return static
     */
    public function withExecutionContext(ExecutionContextInterface $context): static;
}
