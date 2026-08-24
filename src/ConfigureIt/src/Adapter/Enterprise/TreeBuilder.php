<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Enterprise;

/**
 * Builds a nested associative array from a list of path segments and a leaf
 * value. Shared by the cloud parameter-store clients when translating flat
 * vendor keys into a configuration tree.
 */
final class TreeBuilder
{
    /**
     * @param array<string, mixed> $tree
     * @param list<string>         $segments
     * @param mixed                $value
     */
    public static function setPath(array &$tree, array $segments, mixed $value): void
    {
        $node = &$tree;

        foreach ($segments as $segment) {
            if (!isset($node[$segment]) || !is_array($node[$segment])) {
                $node[$segment] = [];
            }

            /** @var array<string, mixed> $node */
            $node = &$node[$segment];
        }

        $node = $value;
    }

    /**
     * Safely coerce an arbitrary JSON scalar into a string without casting
     * `mixed` directly (which the static analysis forbids). Non-scalars become
     * the empty string.
     */
    /**
     * @param mixed $value the (heterogeneous) configuration value to stringify for diffs.
     */
    public static function asString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
