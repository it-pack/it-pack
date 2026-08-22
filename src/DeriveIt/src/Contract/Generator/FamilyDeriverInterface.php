<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Generator;

use Nette\PhpGenerator\InterfaceType;

/**
 * Derives a whole family of classes together so cross-referencing types are
 * rewritten to derived interface FQCNs (F2 / R.2). Extracted so "a family deriver"
 * is polymorphic and assemblable by {@see \ItPack\DeriveIt\DeriveItFactory} at the
 * composition root, closing the hexagonal gap for the secondary derivers.
 */
interface FamilyDeriverInterface
{
    /**
     * @param array<int, class-string> $classes
     * @param array<string, string> $namespacePrefixMap Owned-namespace rewrite, e.g. `App\` => `App\Port\`.
     * @return array<class-string, InterfaceType> keyed by class FQCN
     */
    public function derive(array $classes, array $namespacePrefixMap = []): array;

    /**
     * Auto-discovers the family reachable from a single root class and derives it.
     *
     * @param array<string, string> $namespacePrefixMap
     * @return array<class-string, InterfaceType> keyed by class FQCN
     */
    public function deriveFromRoot(string $rootClass, array $namespacePrefixMap = []): array;

    /**
     * @param array<int, class-string> $classes
     * @param array<string, string> $namespacePrefixMap
     */
    public function deriveSource(array $classes, array $namespacePrefixMap = []): string;

    public function clearCache(): void;
}
