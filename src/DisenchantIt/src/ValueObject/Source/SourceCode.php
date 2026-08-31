<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Source;

/**
 * Concrete, immutable SourceCode. The `code` is held verbatim (never executed or
 * autoloaded) and `identity()` is a cheap content hash, so the same source text
 * always resolves to the same identity regardless of how many SourceCode
 * objects wrap it.
 */
final readonly class SourceCode implements SourceCodeInterface
{
    public function __construct(public string $code)
    {
    }

    public function identity(): string
    {
        return hash('xxh128', $this->code);
    }
}
