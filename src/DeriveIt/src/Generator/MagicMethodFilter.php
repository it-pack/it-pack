<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\MethodFilterInterface;
use ReflectionMethod;

/**
 * Drops magic methods (names starting with "__") unless inclusion is enabled.
 * Constructors/destructors are handled separately by ConstructorFilter so they
 * can be kept when includeMagic is true but dropConstruct/dropDestruct are set.
 */
final readonly class MagicMethodFilter implements MethodFilterInterface
{
    public function __construct(
        private bool $includeMagic = false,
    ) {
    }

    public function keep(ReflectionMethod $method): bool
    {
        return !str_starts_with($method->getName(), '__') || $this->includeMagic;
    }
}
