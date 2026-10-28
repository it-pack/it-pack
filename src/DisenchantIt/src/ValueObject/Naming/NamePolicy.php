<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Naming;

/**
 * Immutable naming policy: an optional prefix, an optional suffix, and a casing
 * transform applied to a constant name (whether generated or supplied by the
 * naming dictionary). Replace, never mutate (value-object contract).
 */
final readonly class NamePolicy implements NamePolicyInterface
{
    // @phpstan-ignore shipmonk.deadMethod
    public function __construct(
        public string $prefix = '',
        public string $suffix = '',
        public NameCasing $casing = NameCasing::None,
    ) {
    }

    public function apply(string $name): string
    {
        $cased = match ($this->casing) {
            NameCasing::UpperSnake => strtoupper($name),
            NameCasing::LowerSnake => strtolower($name),
            NameCasing::None => $name,
        };

        return $this->prefix . $cased . $this->suffix;
    }
}
