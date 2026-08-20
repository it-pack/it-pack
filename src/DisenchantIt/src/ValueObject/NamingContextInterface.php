<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Hints gathered about a numeric literal that let DisenchantIt name it without
 * inventing a name. A number is only promoted when at least one hint is present
 * (`hasHint()`), which is what keeps magic numbers in conditionals/bare
 * expressions from being promoted.
 *
 *   - `$unit`      a unit suffix discovered in the assigned name (Ms, Seconds,
 *                  Bytes, Ttl, ...). Strongest hint.
 *   - `$comment`   a preceding `//`/`#` comment line (e.g. `// seconds`).
 *   - `$variableName` the assigned variable / property / array-key name.
 *
 * All three are nullable; the value object is immutable.
 */
interface NamingContextInterface
{
    public ?string $variableName { get; }

    public ?string $comment { get; }

    public ?string $unit { get; }

    /**
     * True when the literal carries at least one naming hint, i.e. it is worth
     * promoting. A bare numeric literal in a comparison/branch has none.
     */
    public function hasHint(): bool;

    /**
     * Stable signature used to keep equal-valued numbers with DIFFERENT hints
     * in separate promotion groups (so two `100`s named from distinct
     * variables never collide).
     */
    public function signature(): string;
}
