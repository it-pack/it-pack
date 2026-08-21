<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ItPack\DeriveIt\Contract\Generator\MethodFilterInterface;
use ReflectionMethod;

/**
 * Keeps methods whose visibility is within the configured allowed mask.
 */
final readonly class VisibilityFilter implements MethodFilterInterface
{
    public function __construct(
        private int $visibilityMask,
    ) {
    }

    public function keep(ReflectionMethod $method): bool
    {
        return ($method->getModifiers() & $this->visibilityMask) !== 0;
    }
}
