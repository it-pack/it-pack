<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Rewrite;

/**
 * Closed set of PHP class-constant visibilities rendered by
 * ConstantDeclaration::toCodeLine(). Replaces bare 'private'/'public'
 * strings at every emitter call site.
 */
enum Visibility: string
{
    case Private = 'private';
    // @phpstan-ignore shipmonk.deadEnumCase
    case Protected = 'protected';
    case Public = 'public';
}
