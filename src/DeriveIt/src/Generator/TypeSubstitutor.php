<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Rewrites class references inside a ReflectionType to their derived interface
 * FQCNs, keeping the leakage invariant intact across a family of types.
 *
 * Walks named / union / intersection types. Built-in types (int, string, array,
 * mixed, null) and the special self/static/parent designators are left untouched
 * unless explicitly present in the map.
 */
final class TypeSubstitutor
{
    /**
     * @param array<string, string> $map Class FQCN => derived interface FQCN.
     */
    public function __construct(
        private readonly array $map = [],
    ) {
    }

    public function substitute(ReflectionType $type): string
    {
        return $this->walk($type);
    }

    private function walk(ReflectionType $type): string
    {
        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map($this->walk(...), $type->getTypes()));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map($this->walk(...), $type->getTypes()));
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->walkNamed($type);
        }

        return (string) $type;
    }

    private function walkNamed(ReflectionNamedType $type): string
    {
        $name = $type->getName();

        if ($type->isBuiltin() && !in_array($name, ['self', 'static', 'parent'], true)) {
            return $name;
        }

        $resolved = $this->map[$name] ?? $name;

        return ($type->allowsNull() ? '?' : '') . $resolved;
    }
}
