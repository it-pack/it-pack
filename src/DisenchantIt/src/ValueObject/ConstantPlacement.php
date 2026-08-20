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
        public string $type,
        public array $occurrences,
    ) {
    }

    /**
     * The PHP type keyword for this placement's declaration, e.g. `int`,
     * `float`, `string`. Centralized through LiteralKind so the emitted keyword
     * can never drift from the detected kind.
     */
    public function typeKeyword(): string
    {
        return LiteralKind::from($this->type)->toPhpKeyword();
    }
}
