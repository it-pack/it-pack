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
 * mixed, null) are left untouched. The special self/static/parent designators are
 * rewritten when a derived FQCN is supplied for them (Phase 9.1 / A1); otherwise
 * they fall through to the map or stay verbatim.
 *
 * Constant default-value expressions (e.g. `Foo::DEFAULT`) are rewritten via
 * {@see self::rewriteConstantExpression()} so an enum/constant from an adaptee
 * class is mapped to its derived interface when present (A2).
 */
final class TypeSubstitutor
{
    /**
     * @param array<string, string> $map Class FQCN => derived interface FQCN.
     * @param string|null $selfFqcn FQCN to substitute for `self` / `static`.
     * @param string|null $parentFqcn FQCN to substitute for `parent`.
     * @param array<string, string> $namespacePrefixMap Owned-namespace rewrite, e.g. `App\` => `App\Port\`.
     */
    public function __construct(
        private readonly array $map = [],
        private readonly ?string $selfFqcn = null,
        private readonly ?string $parentFqcn = null,
        private readonly array $namespacePrefixMap = [],
    ) {
    }

    public function substitute(ReflectionType $type): string
    {
        return $this->walk($type);
    }

    /**
     * Rewrites a constant reference such as `Foo::BAR` when `Foo` is present in the
     * substitution map. Returns the expression unchanged when there is nothing to
     * rewrite (so adaptee-owned constants survive verbatim).
     */
    public function rewriteConstantExpression(string $expression): string
    {
        if (preg_match('/^(?<class>[A-Za-z_\\\\][A-Za-z0-9_\\\\]*?)::(?<const>[A-Za-z_][A-Za-z0-9_]*)$/', $expression, $m) !== 1) {
            return $expression;
        }

        $class = ltrim($m['class'], '\\');

        // `self` / `static` reference the enclosing class and `parent` its parent;
        // rewrite them to the derived interface FQCN so the port references itself
        // rather than the concrete adaptee (consistent with A1 / EC6).
        if (in_array($class, ['self', 'static'], true) && $this->selfFqcn !== null) {
            return $this->selfFqcn . '::' . $m['const'];
        }
        if ($class === 'parent' && $this->parentFqcn !== null) {
            return $this->parentFqcn . '::' . $m['const'];
        }

        if (isset($this->map[$class])) {
            return $this->map[$class] . '::' . $m['const'];
        }

        // PHP resolves a class-constant reference written without a leading
        // backslash relative to the current namespace, so the captured class may
        // carry an unexpected prefix (e.g. `_Fixture\SimpleBackedEnum` for a global
        // enum). Fall back to the short name so a map keyed by the bare class works.
        $short = ($pos = strrpos($class, '\\')) !== false ? substr($class, $pos + 1) : $class;
        if (isset($this->map[$short])) {
            return $this->map[$short] . '::' . $m['const'];
        }

        return $expression;
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

        if (in_array($name, ['self', 'static'], true) && $this->selfFqcn !== null) {
            $resolved = $this->selfFqcn;
        } elseif ($name === 'parent' && $this->parentFqcn !== null) {
            $resolved = $this->parentFqcn;
        } else {
            $resolved = $this->map[$name] ?? $name;
            $resolved = $this->applyNamespacePrefix($resolved);
        }

        return ($type->allowsNull() ? '?' : '') . $resolved;
    }

    /**
     * Rewrites an owned-namespace class reference to its port namespace when a
     * matching prefix is configured (C2).
     */
    private function applyNamespacePrefix(string $fqcn): string
    {
        foreach ($this->namespacePrefixMap as $from => $to) {
            if (str_starts_with($fqcn, $from)) {
                return $to . substr($fqcn, strlen($from));
            }
        }

        return $fqcn;
    }
}
