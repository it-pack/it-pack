<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for a single promoted constant declaration. The concrete
 * ConstantDeclaration centralises the `const` line shape so every emitter
 * produces identical output.
 */
interface ConstantDeclarationInterface
{
    public string $name { get; }

    public string $valueExpression { get; }

    public string $typeKeyword { get; }

    public Visibility $visibility { get; }

    public function toCodeLine(int $indent = 4): string;
}
