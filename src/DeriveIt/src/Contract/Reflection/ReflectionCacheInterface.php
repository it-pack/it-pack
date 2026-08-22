<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Reflection;

use ReflectionClass;

/**
 * Injectable memoization of {@see ReflectionClass} instances keyed by FQCN, so a
 * whole-repo scan (or repeated derivations) reflects each class at most once and
 * never re-runs `class_exists` for an already-seen type (G18 / E5).
 *
 * Instance-scoped by design — never a static property — so cache state cannot leak
 * across unrelated derivations or mask Infection mutants (see G4 / EC3).
 */
interface ReflectionCacheInterface
{
    public function get(string $fqcn): ?ReflectionClass;

    public function set(string $fqcn, ReflectionClass $reflection): void;
}
