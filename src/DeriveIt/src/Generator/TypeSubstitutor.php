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
final readonly class TypeSubstitutor
{
    /**
     * @param array<string, string> $map Class FQCN => derived interface FQCN.
     * @param string|null $selfFqcn FQCN to substitute for `self` / `static`.
     * @param string|null $parentFqcn FQCN to substitute for `parent`.
     * @param array<string, string> $namespacePrefixMap Owned-namespace rewrite, e.g. `App\` => `App\Port\`.
     */
    public function __construct(
        private array   $map = [],
        private ?string $selfFqcn = null,
        private ?string $parentFqcn = null,
        private array   $namespacePrefixMap = [],
    ) {
    }

    public function substitute(ReflectionType $type): string
    {
        return $this->walk($type);
    }

    /**
     * The effective substitution map (class FQCN => derived interface FQCN) this
     * substitutor was built with, including any self/static seeding. Consumers such
     * as {@see InterfaceDeriver} report this as the applied substitutions so the
     * returned metadata matches what was actually rewritten (e.g. a full family
     * cross-reference map in family mode).
     *
     * @return array<string, string>
     */
    public function getMap(): array
    {
        return $this->map;
    }

    /**
     * FQCN substituted for the `self` / `static` keywords, or null when no such
     * substitution is configured.
     */
    public function selfFqcn(): ?string
    {
        return $this->selfFqcn;
    }

    /**
     * FQCN substituted for the `parent` keyword, or null when there is no parent
     * or no substitution is configured.
     */
    public function parentFqcn(): ?string
    {
        return $this->parentFqcn;
    }

    /**
     * Rewrites class FQCNs inside an already-extracted phpdoc comment (the text
     * returned by {@see MethodSignatureFactory::applyDocblock()}) using the same
     * substitution map as {@see self::substitute()}, so a `@param Foo $x` /
     * `@return Foo` annotation points at the derived interface rather than the
     * concrete adaptee (G8 / EC10). Leading backslashes are preserved.
     */
    public function simplifyDocblock(string $comment): string
    {
        foreach ($this->map as $from => $to) {
            $pattern = '/(\\\\?)' . preg_quote($from, '/') . '\b/';
            $comment = preg_replace_callback(
                $pattern,
                static function (array $matches) use ($to): string {
                    return ($matches[1] !== '' ? '\\' : '') . $to;
                },
                $comment,
            );
        }

        return $comment;
    }

    /**
     * Rewrites a constant reference such as `Foo::BAR` when `Foo` is present in the
     * substitution map. Returns the expression unchanged when there is nothing to
     * rewrite (so adaptee-owned constants survive verbatim).
     */
    public function rewriteConstantExpression(string $expression): string
    {
        if (
            preg_match(
                '/^(?<class>[A-Za-z_\\\\][A-Za-z0-9_\\\\]*?)::(?<const>[A-Za-z_][A-Za-z0-9_]*)$/',
                $expression,
                $m,
            ) !== 1
        ) {
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
        $short = Naming::shortName($class);
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

        $resolved = match (true) {
            $this->selfFqcn !== null && in_array($name, ['self', 'static'], true) => $this->selfFqcn,
            $name === 'parent' && $this->parentFqcn !== null => $this->parentFqcn,
            default => $this->map[$name] ?? $name,
        };

        // Owned-namespace prefix rewriting (C2) applies to the resolved class
        // reference so a family member rendered into a port namespace still points
        // at the port rather than the concrete owned type.
        $resolved = $this->applyNamespacePrefix($resolved);

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
