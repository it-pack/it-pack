<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

final readonly class ConstantPlacement implements ConstantPlacementInterface
{
    /**
     * @param list<LiteralInterface> $occurrences the subset of occurrences this
     *        placement's constant should replace.
     */
    public function __construct(
        public string $className,
        public string $constantName,
        public string $value,
        public array $occurrences,
    ) {
    }
}
