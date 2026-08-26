<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\ValueObject;

use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;

/**
 * Structural contract for a feature-flag definition.
 *
 * Mirrors {@see \ItPack\ConfigureIt\ValueObject\FlagDefinition}; extracted so
 * flag-evaluation consumers depend on the contract rather than the concrete
 * value object.
 */
interface FlagDefinitionInterface
{
    public function isEnabledFor(ExecutionContextInterface $ctx): bool;
}
