<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Naming;

use ItPack\DisenchantIt\Adapter\Naming\ConstantNameResolver;
use ItPack\DisenchantIt\ValueObject\Scope\DisenchantScope;
use ItPack\DisenchantIt\ValueObject\Scope\DisenchantScopeInterface;

/**
 * Port for the immutable naming policy applied to a generated or
 * dictionary-supplied constant name: an optional prefix, an optional suffix, and
 * a casing transform (see {@see NameCasing}).
 *
 * Exists so ConstantNameResolver can depend on an interface (clean layered
 * architecture) rather than the concrete {@see NamePolicy}, mirroring the
 * DisenchantScope / DisenchantScopeInterface split.
 */
interface NamePolicyInterface
{
    public function apply(string $name): string;
}
