<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Rewrite;

/**
 * A block of declaration source text to inject for a promotion, anchored to a
 * class by name.
 *
 *  - `topLevel = false`  → inserted inside the class body, right after its
 *    opening brace (a `private const`, today's behavior).
 *  - `topLevel = true`   → inserted at file top level, after the class's closing
 *    brace (a backed enum, which cannot be nested inside a class).
 *
 * Immutable.
 */
final readonly class Insertion implements InsertionInterface
{
    /**
     * @param list<string> $lines the pre-rendered declaration lines to insert.
     */
    public function __construct(
        public string $className,
        public array $lines,
        public bool $topLevel = false,
    ) {
    }
}
