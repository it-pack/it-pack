<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Rewrite;

/**
 * Port for a single promoted constant declaration. The concrete
 * ConstantDeclaration centralises the `const` line shape so every emitter
 * produces identical output.
 */
interface ConstantDeclarationInterface
{
    // @phpstan-ignore shipmonk.deadProperty.neverRead
    public string $name { get; }

    // @phpstan-ignore shipmonk.deadProperty.neverRead
    public string $valueExpression { get; }

    // @phpstan-ignore shipmonk.deadProperty.neverRead
    public string $typeKeyword { get; }

    // @phpstan-ignore shipmonk.deadProperty.neverRead
    public Visibility $visibility { get; }

    // @phpstan-ignore shipmonk.deadMethod
    public function toCodeLine(int $indent = 4): string;
}
