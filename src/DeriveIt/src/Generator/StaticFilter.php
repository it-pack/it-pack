<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\MethodFilterInterface;
use ReflectionMethod;

/**
 * Drops static methods unless inclusion is explicitly enabled.
 */
final class StaticFilter implements MethodFilterInterface
{
    public function __construct(
        private readonly bool $includeStatic = false,
    ) {
    }

    public function keep(ReflectionMethod $method): bool
    {
        return !$method->isStatic() || $this->includeStatic;
    }
}
