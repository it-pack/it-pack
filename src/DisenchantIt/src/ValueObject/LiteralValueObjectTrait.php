<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Shared read-only helper for literal value objects. Holds no state of its own
 * (a trait must not introduce hidden dependencies) — only a `needle()` default
 * that turns a literal into the substring the rewriter looks for on an
 * occurrence line: the quoted form for strings, the exact raw token for
 * numbers.
 *
 * @phpstan-require-implements LiteralInterface
 */
trait LiteralValueObjectTrait
{
    public function needle(): string
    {
        if ($this->kind === LiteralKind::String->value) {
            return var_export($this->value, true);
        }

        return $this->raw;
    }
}
