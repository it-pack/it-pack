<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Immutable carrier for one promoted constant declaration, shared by the
 * promotion targets (private) and SemConvCollector (public). Centralises the
 * `const` line shape so the emitters can never drift apart.
 */
final readonly class ConstantDeclaration implements ConstantDeclarationInterface
{
    public function __construct(
        public string $name,
        public string $valueExpression,
        public string $typeKeyword,
        public Visibility $visibility = Visibility::Private,
    ) {
    }

    /**
     * Renders `    <visibility> const <type> <name> = <value>;` at the given
     * indentation (4 spaces by default, matching PSR-12 class-body indent).
     */
    public function toCodeLine(int $indent = 4): string
    {
        return sprintf(
            '%s%s const %s %s = %s;',
            str_repeat(' ', $indent),
            $this->visibility->value,
            $this->typeKeyword,
            $this->name,
            $this->valueExpression,
        );
    }
}
