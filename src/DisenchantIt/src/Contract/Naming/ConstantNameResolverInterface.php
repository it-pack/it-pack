<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Naming;

use ItPack\DisenchantIt\Core\Disenchanter;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\Source\NamingContext;

/**
 * Single source of truth for turning a detected literal into its promoted
 * constant name. Centralizes the previously-triplicated `nameFor()` logic that
 * lived in Disenchanter, PerClassConstantStrategy and SameClassConstantStrategy.
 *
 * Strings are named by slug (ConstantNameGenerator); numbers are named from
 * their NamingContext hint (NumericNameGenerator), falling back to the raw
 * value when no hint is available.
 */
interface ConstantNameResolverInterface
{
    public function resolve(LiteralInterface $lit): string;
}
