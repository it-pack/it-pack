<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use Nette\PhpGenerator\InterfaceType;

/**
 * Immutable bundle of everything {@see InterfaceDeriver::build()} computes for a
 * single class: the assembled interface plus the structured surface metadata
 * consumed by {@see InterfaceDeriver::deriveWithResult()} / {@see InterfaceDeriver::analyze()}
 * and the leakage auditor.
 *
 * Replaces the untyped `(object){interface, methods, substitutions, constants}`
 * return that previously leaked untyped shape through the deriver internals (G15).
 */
final readonly class BuildMeta
{
    /**
     * @param list<string> $methods Method names in the derived surface (in order).
     * @param array<string,string> $substitutions Effective type substitutions (FQCN => derived interface FQCN),
     *     including self/static/parent when applied.
     * @param list<string> $constants Constant names lifted into the interface.
     */
    public function __construct(
        public InterfaceType $interface,
        public array         $methods,
        public array         $substitutions,
        public array         $constants,
    ) {
    }
}
