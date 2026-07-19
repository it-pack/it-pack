<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Exception\ConfigurationException;

/**
 * An immutable, validated configuration key within the dotted key space.
 *
 * Dotted keys (e.g. `database.host`, `app.debug`) are a first-class domain
 * concept in ConfigureIt's ubiquitous language, yet across the codebase they were
 * passed around as bare strings and re-walked by hand in three places:
 * {@see ConfigurationSnapshot}, {@see ConfigurationGraph} and
 * {@see ConfigurationPipeline}. This object centralises that semantics so that
 * future segment kinds (array indexes, `cache.pools[0].dsn`) land in exactly one
 * place.
 *
 * A key is immutable; {@see child()} returns a new key. Traversal primitives
 * ({@see get()}, {@see has()}, {@see set()}) operate on a plain nested array tree,
 * which is the interchange shape of {@see ConfigurationSnapshot::all()} and
 * {@see ConfigurationGraph::toArray()}.
 *
 * @see docs/architecture/decisions/ADR-0009-Key-Value-Object.md
 */
final readonly class Key
{
    /**
     * @param list<string> $segments Non-empty, validated path segments.
     */
    private function __construct(
        private array $segments,
    ) {
    }

    /**
     * Parse a dotted key such as `app.debug` into a validated {@see Key}.
     *
     * @throws ConfigurationException when the key is empty or contains a blank segment.
     */
    public static function fromString(string $path): self
    {
        $trimmed = trim($path);

        if ($trimmed === '') {
            throw new ConfigurationException('Configuration key must not be empty.');
        }

        $segments = explode('.', $trimmed);

        foreach ($segments as $segment) {
            if ($segment === '') {
                throw new ConfigurationException(sprintf('Configuration key "%s" contains an empty segment.', $path));
            }
        }

        return new self($segments);
    }

    /**
     * Return a new key with an additional segment appended.
     */
    public function child(string $segment): self
    {
        $trimmed = trim($segment);

        if ($trimmed === '') {
            throw new ConfigurationException('Cannot append an empty segment to a configuration key.');
        }

        return new self([...$this->segments, $trimmed]);
    }

    /**
     * The dotted string representation, e.g. `database.host`.
     */
    public function toString(): string
    {
        return implode('.', $this->segments);
    }

    /**
     * Whether a plain array should be treated as a nested configuration branch
     * (associative) rather than a leaf list value. An empty array is a leaf, not
     * a branch.
     *
     * @param array<mixed> $value
     */
    public static function isAssociative(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    /**
     * Read a value from a nested tree, or $default when any segment is missing.
     *
     * @param array<string, mixed> $tree
     */
    public function get(array $tree, mixed $default = null): mixed
    {
        $cursor = $tree;

        foreach ($this->segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * Whether a value exists at this key in the nested tree.
     *
     * @param array<string, mixed> $tree
     */
    public function has(array $tree): bool
    {
        $cursor = $tree;

        foreach ($this->segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return false;
            }

            $cursor = $cursor[$segment];
        }

        return true;
    }

    /**
     * Write $value at this key inside $tree, creating intermediate arrays.
     *
     * @param array<string, mixed> $tree
     * @param mixed                $value
     */
    public function set(array &$tree, mixed $value): void
    {
        $cursor = &$tree;

        foreach ($this->segments as $segment) {
            if (!is_array($cursor)) {
                $cursor = [];
            }

            $cursor = &$cursor[$segment];
        }

        $cursor = $value;
        unset($cursor);
    }
}
