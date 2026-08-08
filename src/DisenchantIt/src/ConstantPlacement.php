<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

final class ConstantPlacement
{
    /**
     * @param list<StringLiteral> $occurrences the subset of occurrences this
     *        placement's constant should replace.
     */
    public function __construct(
        public readonly string $className,
        public readonly string $constantName,
        public readonly string $value,
        public readonly array $occurrences,
    ) {
    }
}
