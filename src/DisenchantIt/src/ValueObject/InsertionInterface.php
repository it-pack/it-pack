<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for a block of declaration source text to inject for a promotion, anchored
 * to a class by name. `topLevel = false` inserts inside the class body (a
 * `private const`); `topLevel = true` inserts at file top level after the class's
 * closing brace (a backed enum, which cannot be nested in a class).
 */
interface InsertionInterface
{
    public string $className { get; }

    /** @var list<string> */
    public array $lines { get; }

    public bool $topLevel { get; }
}
