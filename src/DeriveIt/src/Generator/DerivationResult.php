<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use Nette\PhpGenerator\InterfaceType;

/**
 * Structured result of a derivation run (D1). Replaces the bare-source return so
 * callers can introspect the derived surface, the FQCN, and applied substitutions
 * without re-parsing the generated source.
 *
 * Immutable value object: all state is public readonly.
 */
final class DerivationResult
{
    /**
     * @param list<string> $methods Method names in the derived surface.
     * @param array<string, string> $substitutions Effective type substitutions (FQCN => derived interface FQCN), including self/parent when applied.
     * @param list<string> $constants Constant names lifted into the interface.
     * @param string|null $source Rendered PHP source. Null when the result was
     *                            produced by `analyze()` (metadata only, no render).
     */
    public function __construct(
        public readonly InterfaceType $interface,
        public readonly string $fqcn,
        public readonly string $shortName,
        public readonly ?string $source,
        public readonly array $methods,
        public readonly array $substitutions = [],
        public readonly array $constants = [],
    ) {
    }
}
