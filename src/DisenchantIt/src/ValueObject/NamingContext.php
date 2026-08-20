<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Default immutable NamingContext. See NamingContextInterface for the field
 * semantics. Constructed once per detected numeric literal during the AST scan
 * (the visitor has the node + source needed to read the hints).
 */
final readonly class NamingContext implements NamingContextInterface
{
    public function __construct(
        public ?string $variableName = null,
        public ?string $comment = null,
        public ?string $unit = null,
    ) {
    }

    public function hasHint(): bool
    {
        return $this->unit !== null
            || $this->comment !== null
            || $this->variableName !== null;
    }

    public function signature(): string
    {
        return implode('|', [
            $this->unit ?? '',
            $this->variableName ?? '',
            $this->comment ?? '',
        ]);
    }
}
