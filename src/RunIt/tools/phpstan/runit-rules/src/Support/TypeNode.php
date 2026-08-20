<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Support;

use PhpParser\Node;

/**
 * Helpers for inspecting declared type nodes (the syntactic `resource`,
 * `?resource`, `resource|null` etc. that appear in signatures and property
 * types).
 *
 * @internal
 */
final class TypeNode
{
    public static function containsResource(?Node $type): bool
    {
        return self::match($type, static fn (string $name): bool => $name === 'resource');
    }

    private static function match(?Node $type, callable $predicate): bool
    {
        if ($type === null) {
            return false;
        }

        if ($type instanceof Node\NullableType) {
            return self::match($type->type, $predicate);
        }

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            foreach ($type->types as $inner) {
                if (self::match($inner, $predicate)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof Node\Identifier) {
            return (bool) $predicate(strtolower($type->name));
        }

        if ($type instanceof Node\Name) {
            return (bool) $predicate(strtolower($type->toString()));
        }

        return false;
    }
}
